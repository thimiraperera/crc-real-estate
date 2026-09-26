=== CRC Real Estate ===
Contributors: thimiraperera
Tags: real estate, property, listings, land
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Real estate listing tools for WordPress.

== Description ==

CRC Real Estate brings the listing features of CRC Properties to WordPress, and is built to run on other real estate websites too.

New versions are delivered from GitHub and install from the normal WordPress Updates screen.

== Installation ==

1. Upload the `crc-real-estate` folder to `/wp-content/plugins/`, or upload the plugin zip from Plugins → Add New → Upload Plugin.
2. Activate the plugin from the Plugins screen.

== Changelog ==

= 0.5.1 =
* Title section: [crc_listing_title] shows the listing's title as an H3.
* Empty style places for the section containers: crc-listing-details, crc-listing-overview, crc-listing-location, crc-listing-inquiry, crc-listing-faq and crc-listing-price.

= 0.5.0 =
* Price card: [crc_listing_price_card] shows a Share button, what the listing is (Land for sale, Property for sale, Property for rent), the price ("/month" for rentals), the price per perch for land, and Call and WhatsApp buttons.
* Price box on each listing, with a price per perch field for land.
* Contact numbers box: give a listing its own phone and WhatsApp numbers.
* Listings → Settings: the default phone and WhatsApp numbers (+94777643264).

= 0.4.6 =
* "Check for updates" sees a new version the moment it is pushed: the version is read through GitHub's API, with the raw file link as a fallback.

= 0.4.5 =
* crc-card uses the site's white color variable directly, with no extra fallback. The plugin's styles load after Elementor's, so the card's padding and corners apply on Elementor containers.

= 0.4.4 =
* crc-card padding: 32px 32px 40px 32px, and 24px 24px 32px 24px on mobile.

= 0.4.3 =
* Info page shows only shortcodes.

= 0.4.2 =
* Responsive styles follow the site's four modes: Desktop (1367px and up), Laptop (1366px to 1025px), Tablet (1024px to 768px) and Mobile (767px and below).
* Info page: the Styles section lists just the class names.

= 0.4.1 =
* Help texts and descriptions are back to their fuller wording.

= 0.4.0 =
* Listing Categories use the normal WordPress category screen again: names and descriptions can be edited; adding, deleting and changing the web address or parent are locked.
* Description section: [crc_listing_description] shows the listing's text on the site.
* New crc-card class for containers: white box, rounded corners, soft shadow.
* No Author box and no Add Media button on listings.
* Shorter help texts.

= 0.3.1 =
* New Listings → Categories page: each category with a short description, its number of listings and a link to them.
* Friendlier, shorter wording on every admin screen: "Main photo", "More photos", "Category" and "Views".

= 0.3.0 =
* Listing categories: Lands, Properties for sale and Properties for rent. Pick one per listing; the list is fixed, so categories can't be added, renamed or deleted.
* A listing needs a featured image and a category to be published.
* Views box: set a listing's view count by hand (e.g. after publishing it again); visitors keep adding to it.
* The photo viewer is always on in the gallery.
* New eye icon on the view count.

= 0.2.1 =
* "Check for updates" now asks GitHub straight away, puts a new version on the Plugins screen with an Update now button, and says why if GitHub can't be reached.
* A new push is noticed within minutes instead of hours.

= 0.2.0 =
* Listings post type.
* Gallery section: the featured image (required) as the large photo, with gallery images beside it, a view count and a full-screen photo viewer. Shortcode: [crc_listing_gallery].
* Listings → Info page with every shortcode and its options.

= 0.1.0 =
* First version with automatic updates from GitHub.
