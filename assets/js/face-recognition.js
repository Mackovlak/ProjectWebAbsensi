/**
 * ==========================================
 * FACE RECOGNITION SYSTEM - Javag Team
 * Menggunakan face-api.js library
 * ==========================================
 */

class FaceRecognitionSystem {
    constructor() {
        this.isModelLoaded = false;
        this.videoElement = null;
        this.canvasElement = null;
        this.stream = null;
        this.detectionInterval = null;
        
        // Settings
        this.settings = {
            scoreThreshold: 0.5,        // Minimum confidence untuk deteksi wajah
            matchThreshold: 0.6,        // Threshold untuk face matching (semakin rendah semakin strict)
            // Ukuran input TinyFaceDetector (kelipatan 32). 160 terlalu kecil:
            // wajah yang agak jauh / redup sering tidak terdeteksi dan kotak
            // wajahnya kurang akurat (descriptor jadi kurang mirip). 320 =
            // default face-api, masih ringan di HP.
            inputSize: 320,
            videoWidth: 640,
            videoHeight: 480
        };
    }

    /**
     * Load face-api.js models
     */
    async loadModels() {
        try {
            console.log('🔄 Loading face recognition models...');
            
            const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';
            
            // Load models yang diperlukan
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
            ]);

            this.isModelLoaded = true;
            console.log('✅ Face recognition models loaded successfully!');
            return true;
        } catch (error) {
            console.error('❌ Error loading models:', error);
            throw new Error('Gagal memuat model face recognition. Periksa koneksi internet Anda.');
        }
    }

    /**
     * Start kamera
     */
    async startCamera(videoElementId) {
        try {
            this.videoElement = document.getElementById(videoElementId);
            
            if (!this.videoElement) {
                throw new Error('Video element tidak ditemukan');
            }

            // Request akses kamera (prioritas kamera depan)
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: this.settings.videoWidth },
                    height: { ideal: this.settings.videoHeight },
                    facingMode: 'user' // Kamera depan
                },
                audio: false
            });

            this.videoElement.srcObject = this.stream;
            
            // Wait for video to be ready
            return new Promise((resolve, reject) => {
                this.videoElement.onloadedmetadata = () => {
                    this.videoElement.play();
                    console.log('✅ Kamera berhasil diaktifkan');
                    resolve(true);
                };
                this.videoElement.onerror = (error) => {
                    reject(new Error('Gagal memulai kamera'));
                };
            });
        } catch (error) {
            console.error('❌ Error starting camera:', error);
            // Pesan + langkah mengizinkan ulang sesuai platform (izin-perangkat.js).
            if (window.AbsenIzin) throw window.AbsenIzin.errorKamera(error);
            throw new Error('Gagal mengakses kamera. Pastikan izin kamera diberikan.');
        }
    }

    /**
     * Stop kamera
     */
    stopCamera() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        if (this.videoElement) {
            this.videoElement.srcObject = null;
        }
        if (this.detectionInterval) {
            clearInterval(this.detectionInterval);
            this.detectionInterval = null;
        }
        console.log('🛑 Kamera dihentikan');
    }

    // Position feedback only: no landmarks or descriptor work on rejected frames.
    async detectFace() {
        if (!this.videoElement || this.videoElement.readyState < 2) {
            throw new Error('Video belum siap. Tunggu kamera aktif...');
        }
        return faceapi.detectSingleFace(this.videoElement, new faceapi.TinyFaceDetectorOptions({
            inputSize: this.settings.inputSize,
            scoreThreshold: this.settings.scoreThreshold
        }));
    }

    /**
     * Capture face descriptor dari video
     */
    async captureFaceDescriptor(input = this.videoElement) {
        if (!this.isModelLoaded) {
            throw new Error('Model belum dimuat. Tunggu sebentar...');
        }

        // A still frame (canvas) passed in is already captured, so only the
        // live video needs to be fully buffered here.
        if (!this.videoElement || (input === this.videoElement && this.videoElement.readyState !== 4)) {
            throw new Error('Video belum siap. Tunggu kamera aktif...');
        }

        try {
            // Deteksi wajah dengan landmarks dan descriptor
            const detection = await faceapi
                .detectSingleFace(
                    input,
                    new faceapi.TinyFaceDetectorOptions({
                        inputSize: this.settings.inputSize,
                        scoreThreshold: this.settings.scoreThreshold
                    })
                )
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!detection) {
                throw new Error('Wajah tidak terdeteksi. Pastikan wajah terlihat jelas di kamera.');
            }

            console.log('✅ Face descriptor captured:', detection.descriptor.length + ' values');
            
            return {
                descriptor: Array.from(detection.descriptor),
                detection: detection.detection,
                landmarks: detection.landmarks
            };
        } catch (error) {
            console.error('❌ Error capturing face:', error);
            throw error;
        }
    }

    /**
     * Compare 2 face descriptors
     */
    compareFaces(descriptor1, descriptor2) {
        if (!descriptor1 || !descriptor2) {
            throw new Error('Descriptor tidak valid');
        }

        // Convert ke Float32Array jika masih array biasa
        const desc1 = descriptor1 instanceof Float32Array ? descriptor1 : new Float32Array(descriptor1);
        const desc2 = descriptor2 instanceof Float32Array ? descriptor2 : new Float32Array(descriptor2);

        // Hitung euclidean distance
        const distance = faceapi.euclideanDistance(desc1, desc2);
        
        // Convert distance ke confidence score (0-100)
        // Semakin kecil distance, semakin mirip (distance 0 = 100% match)
        const confidence = Math.max(0, Math.min(100, (1 - distance) * 100));
        
        // Aturan baru: Minimal 63% sesuai standar perusahaan
        const isMatch = confidence >= 63.0;

        console.log(`🔍 Face comparison: distance=${distance.toFixed(4)}, confidence=${confidence.toFixed(2)}%, match=${isMatch}`);

        return {
            isMatch: isMatch,
            distance: distance,
            confidence: confidence
        };
    }

    /**
     * Draw detection overlay pada canvas
     */
    drawDetection(detection, canvas) {
        if (!canvas || !detection) return;

        const ctx = canvas.getContext('2d');
        const box = detection.detection.box;

        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Draw bounding box
        ctx.strokeStyle = '#00ff00';
        ctx.lineWidth = 3;
        ctx.strokeRect(box.x, box.y, box.width, box.height);

        // Draw landmarks (optional)
        if (detection.landmarks) {
            const landmarks = detection.landmarks.positions;
            ctx.fillStyle = '#ff0000';
            landmarks.forEach(point => {
                ctx.beginPath();
                ctx.arc(point.x, point.y, 2, 0, 2 * Math.PI);
                ctx.fill();
            });  
        }
    }

    /**
     * Validate face quality
     */
    validateFaceQuality(detection, guide = null) {
        const box = detection.detection.box;
        const videoWidth = this.videoElement.videoWidth;
        const videoHeight = this.videoElement.videoHeight;

        if (guide) {
            // Read rendered geometry so responsive layouts and the mirrored preview agree.
            const videoRect = this.videoElement.getBoundingClientRect();
            const rect = guide.getBoundingClientRect();
            if (!videoRect.width || !videoRect.height || !rect.width || !rect.height) {
                return {valid: false, message: 'Video belum siap. Tunggu kamera aktif...'};
            }
            const width = rect.width * videoWidth / videoRect.width;
            const height = rect.height * videoHeight / videoRect.height;
            const centerX = videoWidth * (1 - (rect.left + rect.width / 2 - videoRect.left) / videoRect.width);
            const centerY = videoHeight * (rect.top + rect.height / 2 - videoRect.top) / videoRect.height;
            // Detector boxes cover the face, while the oval includes the whole head.
            if (box.width < width * 0.45 || box.height < height * 0.45) {
                return {valid: false, message: 'Dekatkan wajah sedikit.'};
            }
            if (box.width > width * 0.95 || box.height > height * 0.95) {
                return {valid: false, message: 'Jauhkan wajah sedikit.'};
            }
            const dx = (box.x + box.width / 2 - centerX) / (width * 0.2);
            const dy = (box.y + box.height / 2 - centerY) / (height * 0.2);
            if (dx * dx + dy * dy > 1) {
                return {valid: false, message: 'Posisikan wajah di tengah oval.'};
            }
            return {valid: true, message: 'Tahan sebentar...'};
        }

        // Cek ukuran wajah (minimal 20% dari frame)
        const faceArea = box.width * box.height;
        const frameArea = videoWidth * videoHeight;
        const faceRatio = faceArea / frameArea;

        if (faceRatio < 0.1) {
            return { valid: false, message: 'Wajah terlalu kecil. Dekatkan wajah ke kamera.' };
        }

        if (faceRatio > 0.7) {
            return { valid: false, message: 'Wajah terlalu besar. Jauhkan sedikit dari kamera.' };
        }

        // Cek posisi wajah (harus di tengah)
        const centerX = box.x + box.width / 2;
        const centerY = box.y + box.height / 2;
        const videoCenterX = videoWidth / 2;
        const videoCenterY = videoHeight / 2;

        const offsetX = Math.abs(centerX - videoCenterX) / videoWidth;
        const offsetY = Math.abs(centerY - videoCenterY) / videoHeight;

        if (offsetX > 0.3 || offsetY > 0.3) {
            return { valid: false, message: 'Posisikan wajah di tengah kamera.' };
        }

        return { valid: true, message: 'Kualitas wajah baik' };
    }

    /**
     * Calculate Eye Aspect Ratio (EAR) for blink detection
     */
    getEyeAspectRatio(landmarks) {
        const points = landmarks.positions;
        
        const getDistance = (p1, p2) => {
            return Math.sqrt(Math.pow(p1.x - p2.x, 2) + Math.pow(p1.y - p2.y, 2));
        };
        
        const getEAR = (eye) => {
            const A = getDistance(eye[1], eye[5]);
            const B = getDistance(eye[2], eye[4]);
            const C = getDistance(eye[0], eye[3]);
            return (A + B) / (2.0 * C);
        };

        const leftEye = [points[36], points[37], points[38], points[39], points[40], points[41]];
        const rightEye = [points[42], points[43], points[44], points[45], points[46], points[47]];

        const leftEAR = getEAR(leftEye);
        const rightEAR = getEAR(rightEye);

        return (leftEAR + rightEAR) / 2.0;
    }

    /**
     * Check if the current landmarks pass the specified challenge
     */
    checkLiveness(landmarks, challengeType) {
        if (!landmarks) return false;

        if (challengeType === 'blink') {
            const ear = this.getEyeAspectRatio(landmarks);
            // Threshold for blinking is usually below 0.25, but 0.28 is better for partial blinks/low res
            console.log('EAR:', ear.toFixed(3));
            return ear < 0.28;
        }
        return false;
    }

    /**
     * Helper: Check if browser supports required features
     */
    static checkBrowserSupport() {
        const errors = [];

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            errors.push('Browser tidak mendukung akses kamera');
        }

        if (!window.faceapi) {
            errors.push('Library face-api.js tidak dimuat');
        }

        return {
            supported: errors.length === 0,
            errors: errors
        };
    }
}

// Export untuk digunakan di file lain
window.FaceRecognitionSystem = FaceRecognitionSystem;