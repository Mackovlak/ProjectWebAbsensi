# id-holidays — Indonesian public holiday generator

Generates Indonesia's national holiday calendar (`hari libur nasional`) for
any year, computed from calendar math instead of a paid or stale third-party
API. Built for feeding test/sample data into an attendance app.

## Why not just call an API?

The free community ones (dayoffapi, api-harilibur, etc.) exist, but they're
snapshots someone updated by hand — so they lag behind the current year and
you're stuck waiting on someone else's git history. This generates the dates
itself from the actual calendar rules behind Indonesia's holidays, so it
works for any year, including ones nobody has published data for yet.

## How accurate is it, honestly

Indonesia's holidays come from four totally different calendar systems, and
they don't all have the same accuracy ceiling:

| Type | Holidays | Method | Accuracy |
|---|---|---|---|
| Fixed Masehi date | New Year, Labor Day, Pancasila Day, Independence Day, Christmas | Hardcoded month/day | **Exact**, always |
| Christian (Western Easter) | Good Friday, Easter, Ascension | Meeus/Jones/Butcher algorithm | **Exact**, always |
| Islamic (Hijri) | Isra Mi'raj, Idul Fitri, Idul Adha, Islamic New Year, Maulid Nabi | Tabular/arithmetic Hijri calendar | **±0–1 day.** Indonesia's Kemenag confirms these by real moon-sighting (rukyat), which can differ by a day from pure arithmetic. Verified against 2025 & 2026 official SKB dates — max observed deviation was 1 day. |
| Lunisolar (Chinese/Balinese Saka/Buddhist) | Imlek, Nyepi, Waisak | Lookup table, seeded with real 2025/2026 SKB dates | **Exact for years on file, missing otherwise.** These need real astronomical calculation per calendar tradition — not worth faking. The tool errors clearly instead of guessing. |
| Cuti bersama (joint leave) | Extra bridge days | Reference data only (2025/2026) | **Not calculable at all** — it's pure government discretion, announced 3–4 months ahead each year. |

Run `node validate.js` any time to re-check the Hijri/Christian math against
the real, published 2025/2026 dates.

## Usage

```bash
node generate.js 2027
```

Prints the calendar and writes `data/generated/holidays-2027.json`:

```json
{
  "year": 2027,
  "national_holidays": [
    { "date": "2027-01-01", "day": "Jumat", "name": "Tahun Baru Masehi", "type": "fixed", "confidence": "exact" },
    ...
  ],
  "cuti_bersama": []
}
```

Every entry carries its `type` and `confidence` so your attendance app (or
whoever's reading the JSON) can decide how strictly to trust each one —
e.g. treat `estimated` Hijri dates as provisional until you confirm them
closer to the year, or filter cuti_bersama out entirely if your company
doesn't observe it.

## Keeping it current year to year

Two things in this repo need a manual top-up once a year — everything else
just runs:

1. **`lib/lunisolar.js`** — add Imlek/Nyepi/Waisak once you know them.
   Imlek can safely be pre-filled years ahead (it's a fixed astronomical
   calendar); Nyepi and Waisak are usually only published 1–2 years out by
   PHDI / Kemenag / WALUBI.
2. **`data/cuti-bersama.json`** — add the year's cuti bersama once SKB 3
   Menteri is signed (typically Sept–Oct of the year before, e.g. 2026's
   was signed 19 Sept 2025).

If you run `generate.js` for a year before adding these, it still gives you
every holiday it *can* compute and clearly flags what's missing rather than
silently guessing.

## Integrating with your attendance app

This outputs plain JSON, so wiring it in is up to whatever's easiest on
your side:
- Run it once a year (or in a cron/n8n workflow) and import the JSON into
  your holidays table.
- Wrap `generate.js` in a tiny HTTP endpoint if you want your PHP app to
  fetch it live instead.
- The whole thing is ~150 lines of plain arithmetic with no dependencies —
  if you'd rather have it directly in PHP, it ports over in a few minutes
  (happy to do that port if useful).

## Files

```
lib/julian.js       Gregorian <-> Julian Day Number (shared base for the rest)
lib/christian.js     Easter, Good Friday, Ascension (exact)
lib/hijri.js         Tabular Hijri <-> Gregorian conversion
lib/lunisolar.js      Imlek/Nyepi/Waisak lookup table
lib/holidays.js       Combines everything into one year's calendar
data/cuti-bersama.json  Known cuti bersama, by year
generate.js          CLI entry point
validate.js          Sanity-checks the math against real 2025/2026 dates
```
