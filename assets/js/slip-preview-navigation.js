// Keep salary-slip previews inside installed PWAs. Browser tabs keep their targets.
(() => {
    const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true;
    const isSlipReport = value => {
        const url = new URL(value, window.location.href);
        return url.origin === window.location.origin &&
            url.pathname.endsWith('/laporan_slip_batch.php');
    };

    // The admin form changes its action when the report type changes, so inspect
    // the final action on submit, after the existing validation/date conversion.
    document.addEventListener('submit', event => {
        const form = event.target;
        if (event.defaultPrevented || !isStandalone() || !isSlipReport(form.action)) return;
        form.target = '_self';
        if (event.submitter) event.submitter.removeAttribute('formtarget');
        if (event.submitter && event.submitter.name === 'action') {
            event.submitter.value = 'preview';
        } else {
            let action = form.querySelector('input[name="action"]');
            if (!action) {
                action = document.createElement('input');
                action.type = 'hidden';
                action.name = 'action';
                form.appendChild(action);
            }
            action.value = 'preview';
        }
    });

    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || !isStandalone() || !isSlipReport(link.href)) return;
        const url = new URL(link.href);
        url.searchParams.set('action', 'preview');
        link.href = url.href;
        link.target = '_self';
    });
})();
