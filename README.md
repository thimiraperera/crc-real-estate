# CRC Real Estate

Real estate listing tools for WordPress, built for [CRC Properties](https://www.crcproperties.lk) and ready to run on other real estate websites.

## Requirements

- WordPress 5.8 or later
- PHP 7.4 or later

## Installation

1. Upload the `crc-real-estate` folder to `wp-content/plugins/`, or upload a zip of it from **Plugins → Add New → Upload Plugin**.
2. Activate **CRC Real Estate**.

## Usage

Add listings under **Listings** in the WordPress admin. Every shortcode, with its options and examples, is listed under **Listings → Info**.

## Updates

Every site running the plugin gets new versions from the `main` branch of this repository, through the normal WordPress **Dashboard → Updates** screen.

To publish an update:

1. Raise the `Version` number in the header of `crc-real-estate.php`.
2. Add an entry under `== Changelog ==` in `readme.txt`.
3. Commit and push to `main`.

WordPress checks for updates twice a day. To check straight away, click **Check for updates** under the plugin on the Plugins screen. After a push, GitHub can take a few minutes to serve the new version.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
