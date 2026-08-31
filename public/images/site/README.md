# Public website photography

The public site is **image-ready**: each hero/header below shows a photograph
**only when the file exists at the path shown**; otherwise the page renders the
clean design (no broken images). Files are optimised WebP, kept small and to the
aspect ratios below to avoid layout shift.

These slots are currently filled with **real SMS Environmental Alliance field
photos** (environmental monitoring, effluent treatment and on-site assessment).

| Path (relative to `public/`)           | Where it appears            | Size / ratio   | Subject |
|----------------------------------------|-----------------------------|----------------|---------|
| `images/site/hero-industrial.webp`     | Homepage hero (right)       | 1200×960, 4:3  | Noise / emission monitoring on plant equipment. |
| `images/site/home-fieldwork.webp`      | Homepage mid-page band      | 1600×600, 16:6 | Ambient air quality monitoring (outdoor). |
| `images/site/services-monitoring.webp` | Services page header band   | 1600×600, 16:6 | Effluent treatment plant (ETP). |
| `images/site/training-workshop.webp`   | Training page header band   | 1600×600, 16:6 | On-site assessment / team briefing. |
| `images/site/about-facility.webp`      | About page header band      | 1600×600, 16:6 | Technician monitoring a finishing line. |

To replace any image, drop a new optimised WebP at the same path/size — no code
change needed. Alt text lives in the relevant Blade view; update it if a new
image differs meaningfully. Keep the Verify and Contact pages image-free.
