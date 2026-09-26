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

* Listings with a main photo, more photos, categories and view counts
* Photo gallery with a full-screen photo viewer
* Price card with the price, a Share button, and Call and WhatsApp buttons
* Property overview with the main details and a See More popup of details by category
* Property features to tick, grouped by category, with a See More popup
* Location map with the area around the property, while the exact place stays in the admin
* Shortcodes for every section, ready to place in Elementor
* Updates straight from GitHub, checked and installed from Listings → Settings

== Installation ==

1. Upload the `crc-real-estate` folder to `/wp-content/plugins/`, or upload the plugin zip from Plugins → Add New → Upload Plugin.
2. Activate the plugin from the Plugins screen.

== Changelog ==

= 0.13.2 =
* The Location map on listing pages was blank. It asked the area circle for its size before the map had a place to show, which stopped it. It now sets the view from the area's centre and size first, then draws the map and the circle.

= 0.13.1 =
* Maps work on sites that tell browsers not to share their address with other sites (Referrer-Policy: same-origin, which Cloudflare's "Add security headers" adds). OpenStreetMap blocks map tiles without it, which showed as "Access blocked" squares. The map tiles and the place search now send the site's address, never the page's.

= 0.13.0 =
* Location section: [crc_listing_location] shows an OpenStreetMap map with the area around the property (15 km by default), never the exact place. The area's centre is kept a secret distance from the property and shifts a little each day, and its size changes a little too, so the place can't be worked out.
* Location box on the listing screen: mark the exact place on the map by clicking or dragging the pin, search for places, paste latitude and longitude, and keep a Google Maps link. Only people who edit listings see them.
* The map can be turned off for a listing. With no map, the container with the class crc-listing-location is hidden.
* Listings → Settings → Map: the area size, the starting point for new listings (Galle), and another map style with its credit if wanted, with a short guide. No API key is needed.

= 0.12.0 =
* Updates are in Listings → Settings: the installed and latest versions, Check for updates, a button to update to the new version, and What's new. The Plugins screen has a Settings link instead of Check for updates.
* Property overview: Availability is a drop-down with Available Now, Available Soon, Under Offer, Sold and Rented. A value typed before stays until it is changed.
* Popups are at most 70% of the screen height on desktops and laptops, and 90% on tablets and phones.

= 0.11.0 =
* Property features section: [crc_listing_features] shows the first four features with check marks, and See More, which opens the Property Features popup with all of them in groups.
* Property features box on the listing screen: ready-made features to tick, in groups for the listing's category (Legal and documents, Land features, Home features, Security, Tenants and Nearby), Add feature in each group, and groups of your own. It uses the same grey cards as the Property overview box.
* The listing screen boxes share one script and one set of styles.

= 0.10.1 =
* Details that come from the Price box (Price per perch, Price and Rent) and the worked-out price per perch show as locked fields in their groups: the value like the other fields, with Rs., a lock icon and a note. They can't be typed in, and they follow the Price box as it is typed.

= 0.10.0 =
* On the listing screen, the ready-made groups (Size and price, Access and road and the others) use the same grey card as the listing's own groups.
* Each ready-made group has Add detail, for details of the listing's own. They show in the popup after that group's other details, and can be dragged into order or removed. Groups for another category keep their added details.

= 0.9.0 =
* The See More popup's ready-made groups follow the listing's category: lands have Size and price; properties for sale have Size and layout and Price and terms; properties for rent have Size and layout and Rent and terms. Access and road and Utilities are there for every category.
* New details: Bedrooms, Bathrooms, Ensuite bathrooms, Floor area (sq ft or sq m), Storeys, Parking, Furnishing, Maintenance fee, Bank loan, Advance payment, Minimum lease, Utility bills, Electricity and Water supply.
* Price and Rent come from the Price box. For properties for sale, the price per perch is worked out from the price and the land extent.
* On the listing screen, the groups change as soon as another category is chosen. Land extent, Price type and Maintenance fee are shared by the groups that have them, so they keep one value.
* See More is font-weight 500.

= 0.8.0 =
* See More popup: two ready-made groups, Size and price and Access and road, filled in on the listing screen. Each detail has the right kind of field: a number with its unit (perches, Acres, Hectares, ft, m or km) or a list to choose from (price basis, price type, road type, access and facing direction). Price per perch comes from the Price box, so it is typed once.
* Details and groups left empty don't show on the site.
* In the popup, detail names are weight 600 and values weight 400, both in the 6e3d619 color.
* A listing's own groups stay for anything else and show after the ready-made ones.

= 0.7.0 =
* Property overview section: [crc_listing_overview] shows the property type, offered for, availability and listed by in boxes with icons, and See More, which opens a popup with those boxes and all the other details in groups with check marks.
* Property overview box on the listing screen: the four details with suggestions, and groups of other details for the See More popup. Groups and details can be added, removed and dragged into order.
* One popup style for all popups on listing pages: the title on the left, the close button on the right, and a smooth opening and closing animation. On phones it slides up from the bottom.

= 0.6.0 =
* Price and Contact buttons boxes use two columns on desktop and laptop screens, and one column on tablets and phones.
* Each listing category has a Caption, changed on the Listing Categories screen. The defaults are Land for sale, Property for sale and Property for rent. It shows above the price in the site's primary color.
* Class names use single hyphens, for example crc-price-label instead of crc-price__label.
* The Share button keeps its own look when hovered, focused or pressed, so the theme's button hover styles don't change it.

= 0.5.2 =
* Call and Message button texts can be changed: defaults in Listings → Settings, and each listing can have its own. {number} shows the number.
* The Contact numbers box is now called Contact buttons.

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
