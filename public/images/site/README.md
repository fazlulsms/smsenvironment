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

## The three photos supplied for launch

Map the supplied photos to these files:

- **Air-quality monitoring at the refinery** → `hero-industrial.webp` (homepage hero)
- **Water-quality sampling at the ETP/wastewater plant** → `services-monitoring.webp` (Services)
- **Training/presentation session** → `training-workshop.webp` (Training)

Resize + convert to optimised WebP (ImageMagick example — run in this folder):

```bash
magick hero-source.jpg      -resize 1200x960^   -gravity center -extent 1200x960 -quality 82 hero-industrial.webp
magick services-source.jpg  -resize 1600x600^   -gravity center -extent 1600x600 -quality 82 services-monitoring.webp
magick training-source.jpg  -resize 1600x600^   -gravity center -extent 1600x600 -quality 82 training-workshop.webp
```

(or `cwebp -q 82 -resize 1200 960 hero-source.jpg -o hero-industrial.webp`). Once
the files are in this folder the site picks them up automatically — no code change.

Do **not** add photography to the Verify pages or the Contact form (kept clean).
