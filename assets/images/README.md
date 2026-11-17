# Project imagery

Generated with the built-in imagegen tool on 2026-10-29, then resized and encoded as WebP with PHP GD for local web delivery. No external image host is required. Original outputs remain in the generator's default directory. Product images are generic illustrations, not manufacturer specifications or photographs of actual stock. Replace them using Inventory > Edit > Product picture.

## Saved assets

- `security-home.webp`: 1600-pixel-wide home security hero, used by the landing page and dashboard.
- `products/outdoor-camera.webp`: outdoor bullet camera.
- `products/indoor-camera.webp`: indoor turret camera.
- `products/network-recorder.webp`: network recorder.
- `products/surveillance-drive.webp`: surveillance hard disk drive.
- `products/cat6-cable.webp`: Ethernet cable.
- `products/installation-kit.webp`: junction box and connectors.
- `products/solid-state-drive.webp`: SATA SSD.
- `products/desktop-memory.webp`: desktop RAM.

The product files are 640 pixels wide. Filename mapping for seeded SKUs is in `app/Inventory/images.php`. Custom uploads take priority over these sample illustrations.

## Final generation prompts

Home hero:

> Use case: photorealistic-natural. Asset type: photographic hero image for TechTrack, a CCTV installation and home security planning website. Create a premium architectural photograph of a contemporary two-story tropical Philippine home at blue hour. A realistic compact white bullet CCTV camera is mounted beneath the porch eave in the upper right foreground, directed toward the entrance and driveway; the modern house, warm interior window lights, concrete textures, timber details, and restrained tropical greenery fill the background. Welcoming and calm, refined teal evening shadows and warm amber architectural lighting. Wide landscape composition, realistic camera hardware and mounting, crisp architectural detail, tasteful editorial photography, no people, no text, no logos, no watermarks, no diagrams, no UI. The image should crop well into a wide hero banner or a near-square card. This is an illustrative home, not a customer installation.

Each product was generated separately with the following template, substituting the subject listed below:

> Use case: product-mockup. Asset type: square ecommerce product photograph for a CCTV and computer parts catalog. Subject: {subject}. One clean professional studio photograph, pale cool gray seamless background, generous breathing room around the complete object, realistic proportions and materials, soft grounded shadow, premium softbox lighting, sharp focus, object fills about 65 percent of frame. No text, no brand, no logos, no watermark, no border or UI. This is a generic illustrative product image, not a claim about a specific manufacturer.

| Asset | Subject |
| --- | --- |
| outdoor-camera | a single unbranded white PoE outdoor bullet CCTV security camera, black circular lens face, short adjustable mounting bracket and round mounting base, three quarter view |
| indoor-camera | a single unbranded white indoor turret CCTV security camera with a black circular lens, compact round base, three quarter view |
| network-recorder | a single unbranded black eight channel CCTV network video recorder, slim rectangular metal chassis, discreet front status lights and USB port, three quarter view |
| surveillance-drive | a single unbranded 3.5 inch surveillance hard disk drive, silver metal top and dark circuit base, simple blank white label, three quarter view |
| cat6-cable | a neatly coiled blue Cat6 Ethernet cable with transparent RJ45 connectors visible, three quarter view |
| installation-kit | a small unbranded CCTV installation accessory kit: one white weatherproof square junction box, two clear RJ45 connectors and two black cable glands, arranged neatly |
| solid-state-drive | a single unbranded slim black 2.5 inch SATA solid state drive with its SATA connectors visible, matte metal case, three quarter view |
| desktop-memory | a single unbranded green DDR4 desktop RAM module with black rectangular memory chips and gold contact edge, displayed diagonally |
