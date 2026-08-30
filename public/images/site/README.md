# Public website photography — assets to supply

The public site is **image-ready**: each hero/header below shows a licensed
photograph **only when the file exists at the path shown**. Until then, the page
renders the approved clean design (no broken images, no empty boxes). Drop the
optimised files in with these exact names and they appear automatically — no code
change needed.

Use only images you have the right to use (SMSEA-owned, or properly licensed for
commercial website use). Prefer **WebP**, optimised (aim < ~250 KB each), and keep
the aspect ratios below to avoid layout shift.

| Path (relative to `public/`)            | Where it appears        | Recommended size / ratio | Subject |
|-----------------------------------------|-------------------------|--------------------------|---------|
| `images/site/hero-industrial.webp`      | Homepage hero (right)   | ~1200×960, 4:3           | Environmental professional monitoring/testing at an industrial facility (air/emission/parameter monitoring, inspection, sampling). Industry + environment + technical expertise. |
| `images/site/services-monitoring.webp`  | Services page header band| ~1600×600, 16:6         | Environmental testing / monitoring at an industrial facility. |
| `images/site/training-workshop.webp`    | Training page header band| ~1600×600, 16:6         | Professional environmental/technical training or capacity-building session. |

Avoid: generic nature-only stock (forests, hands holding plants, isolated
leaves, globes), posed corporate handshakes, watermarked images, or competitor
photography. The look should read as *industrial environmental expertise*, not a
generic eco/NGO site.

Alt text is already set in the templates and describes the intended subject; if a
final image differs meaningfully, update the `alt` in the relevant Blade view.

Do **not** add photography to the Verify pages or the Contact form (kept clean).
