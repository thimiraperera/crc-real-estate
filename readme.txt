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
* Inquiry form that checks names, phone numbers and emails as people type, with optional hCaptcha; inquiries are emailed and kept in the admin
* FAQs that open smoothly, one at a time, set for each category and each listing, with FAQ structured data for search engines
* Import & Export: add or change many listings at once from a spreadsheet (CSV), with photos downloaded from their links, a sample file, and an export of every listing
* Download listing: a zip file with a page of all the listing's details, its photos in full size and the inquiries about it
* Owner details kept privately with each listing, never shown on the website
* Photo privacy: the place a photo was taken (its GPS position) is removed from uploaded and imported photos
* Keyword ticker: a full-width row of keywords that moves on its own, stops under the mouse, and can be dragged and thrown; keywords import and export as JSON
* Category carousel: square picture cards with a title and a View Properties button, moved with arrows or dragging, running on to the edge of the window; one card with dots on phones
* Tags under the title: the district, land extent, bedrooms, bathrooms, floor area and property type as rounded tags with icons
* Districts: Sri Lanka's 25 districts, picked from a search box that fills itself in from the map
* Suggested names and short links, such as Bare land for sale in Galle and bare-land-for-sale-galle, used by themselves when the title is left empty
* FAQs for any page: questions and answers set on the Widgets page, one open at a time, with FAQ schema for search engines
* Testimonials: their own menu with a name, a star rating in halves and the testimonial, shown in a carousel that goes round and round, with a random choice on each visit
* Listing carousel: the latest or most popular listings in cards, a tab for each category, moved with arrows or dragging, running on to the edge of the window
* Search: a search box for any page with a tab for each category, town and district suggestions as people type and rounded dropdowns for size, price and rooms; filters and results with sorting and numbered pages for the listing archives (all listings, and each category, district and town), designed with archive templates
* Towns: typed on each listing, with the towns already used suggested, so people can search by town, district or Colombo zone
* Shortcodes for every section, ready to place in Elementor
* Updates straight from GitHub, checked and installed from Listings → Settings

Country flags: flag-icons by lipis (MIT license), https://github.com/lipis/flag-icons. Maps: Leaflet and OpenStreetMap.

== Installation ==

1. Upload the `crc-real-estate` folder to `/wp-content/plugins/`, or upload the plugin zip from Plugins → Add New → Upload Plugin.
2. Activate the plugin from the Plugins screen.

== Changelog ==

= 0.25.0 =
* The search works on the listing archives, for archive templates made with a theme builder: All Listings Archive (/listing/, new), All Listing Categories Archive (/listings/lands/), All Districts Archive (/district/galle/) and All Towns Archive (/town/hikkaduwa/). Put [crc_listing_filters] and [crc_listing_results] (and [crc_search_heading] if you like) in the template; on each archive they show that archive's listings.
* On those archives the archive's own list of listings follows the search in the address (the place, the choices, the order and how many a page), so its numbered pages are WordPress's own, such as /listings/lands/page/2/, and the theme builder's own posts widgets show the same listings.
* Listings → Widgets → Search: Listings a page (12 to start with), and where the listings show: the listing archives (as it starts), or a normal page you choose, which the listing archives then show.
* The search box and Show listings lead to the archives: /listing/ for every listing and each category's own archive.
* A place that isn't a town or district is still looked for in the listings' titles, now without making the page a search results page.

= 0.24.0 =
* Search box: [crc_listing_search] for any page, as in the design. A white card (24px corners, a soft shadow) with a tab for each category, named as the categories are and underlined like the listing carousel's, and a place box with a pin and "Town, district or Colombo zone", with the green Search button and its magnifier inside it. Under the card are the chosen tab's own choices as rounded dropdowns: land size, the highest price per perch and the property type for land; bedrooms, the highest rent or price and the property type for homes (only property types the listings have). On phones the tabs can be swiped and the button shows its magnifier only.
* Place suggestions: as people type, the place box suggests the districts and towns that have listings in the chosen category, each with its district, and Colombo zones by their number or by the area people know, such as Kollupitiya for Colombo 3. The arrow keys and Enter, or a click, pick one.
* Search opens the category's own page, such as /listings/lands/?location=Galle&size=10-20, with only the choices that were made.
* Listings page: put [crc_listing_filters] and [crc_listing_results] on a page at /listing/ (or choose the page on Listings → Widgets → Search). Category links such as /listings/lands/, district links such as /district/galle/ and town links such as /town/hikkaduwa/ show that page with their category, district or town chosen, like a normal WordPress archive, each with its own title and address for search engines (also with Yoast SEO and Rank Math).
* Filters: [crc_listing_filters], for beside or above the results: what people are looking for, the place (with the same suggestions), the price or rent, and for land the price per perch and land size, for homes bedrooms, bathrooms and furnishing, and the property type. Show listings opens the category's page; Clear all takes the choices off. The fields and labels keep your form styles from Elementor. On phones the filters fold away behind a Filters bar showing how many are chosen.
* Results: [crc_listing_results] shows the matching listings in the listing carousel's cards, three across (columns="…" changes it; laptops show at most 3, tablets 2 and phones 1), with how many were found, Sort by (newest, price low to high or high to low, most viewed, and for land the price per perch and the largest land) and numbered pages with the round arrows. [crc_search_heading] is a heading that says what is being looked at, such as "Land to buy in Galle".
* Towns: each listing has a Town field in the District and town box, under the district. As you type, the towns already used are suggested with their district, so a town is written the same way every time; a new town is added when the listing is saved. Picking a suggested town fills in an empty district, and a place chosen on the map fills in an empty town (Colombo's zones from their postcode). Listings → Towns lists every town to put a spelling right, All Listings has a Town column, and Import & Export has a Town column.
* The land size in perches and the price per perch are kept ready for the search on each listing, worked out again whenever a listing changes, and the search's lists are cleared from the LiteSpeed cache when listings change.

= 0.23.0 =
* Listing carousel: [crc_listing_carousel] shows listings in cards with a tab for each category (named as the categories are), 10 a tab. show="latest" has the newest listings, for Latest Verified Listings, and show="popular" the most viewed, for Popular Listings; count="…" and categories="…" change it. A tab without listings doesn't show.
* Each card: the main photo (it zooms a little under the mouse) with the district on a white pill, the land extent or bedrooms and the property type as tags, the title as an H6 in var(--e-global-color-6e3d619), the price as an H3 and the price per perch as an H6, both in var(--e-global-color-primary), 9px apart, and View Details with the arrow in custom-btn-1-lite. Every colour is one of the site's, with color-mix for shades.
* It works like the category carousel, with its round arrows and its script: it can be dragged, the cards run on to the right edge of the window, the arrows fade at the ends, and phones show one card at a time with dots. It doesn't go round and round. The tabs work with the mouse and the keyboard.
* The keyword ticker's settings moved from Listings → Settings to a Keyword ticker tab in Listings → Widgets. The keywords stay as they were.
* The round arrows' shadows use the site's colours.

= 0.22.0 =
* Testimonials: a Testimonials menu of its own (not under Listings) with All Testimonials, Add New Testimonial and Settings. Each testimonial has a name (where a title usually goes), a star rating from 0.5 to 5 that goes up in halves, and what they said. The list shows each one's stars and the start of its words.
* [crc_testimonials]: the testimonials in cards (32px padding, 16px corners, var(--e-global-color-3581a59), a 1px border of 10% var(--e-global-color-1ead78b); 24px 16px padding on phones), 24px apart. The name is an H6 with no margins in var(--e-global-color-6e3d619), then 20px SVG stars 12px under it, then the words 16px under the stars in var(--e-global-color-text), with no margin under the last paragraph.
* The carousel goes round and round: three at a time on desktops and laptops, two on tablets and one on phones. It can be dragged or swiped, and a throw glides on and settles on a card; the arrow keys move it too. It moves on by itself and waits while the mouse or the keyboard is on it, off screen, or for people who ask for less motion.
* A new random choice of testimonials on each visit, even when the page comes from the cache. Testimonials → Settings sets how many (10 to start with) and how often it moves on (every 5 seconds; 0 keeps it still); count="…" and delay="…" change them on one page. Saving clears the LiteSpeed cache.

= 0.21.0 =
* FAQs for any page: [crc_faqs] shows the site's own questions as white cards (16px corners, a 1px border of 3% black in white, a soft shadow), 24px apart and up to 800px wide. Questions have 16px 24px padding, weight 500 and var(--e-global-color-6e3d619); answers use var(--e-global-color-text); the text keeps the site's size. Each question opens smoothly with the chevron arrows on the right, the first is open at the start, and only one is open at a time. open="none" starts with all closed.
* FAQ schema: the questions are given to search engines as FAQPage structured data, once a page, together with a listing's questions when both are on the page. schema="no" leaves it out for pages where an SEO plugin does it.
* Listings → Widgets has tabs: Category carousel and FAQs. On the FAQs tab, add questions with their answers (an empty line starts a new paragraph), and drag them or use the arrows to change the order. Each tab saves on its own. Saving clears the LiteSpeed cache.

= 0.20.0 =
* Tags under the title: [crc_listing_tags] shows the listing's key details as rounded tags with icons, like Galle, 20 Acres and Bare Land. Lands show the district, land extent and property type; properties for sale and for rent show the district, bedrooms, bathrooms, floor area and property type. They come from what is already filled in, a tag without a value doesn't show, and show="…" picks other ones. The pin is your icon; the ruler, area, bed and bath icons are new, and the property type uses the floor plan icon.
* Districts: Sri Lanka's 25 districts with their provinces, as a fixed list (Listings → Districts) with pages at /district/galle/, a District column and filter in All Listings, and a District column in Import & Export.
* District box on the listing screen: a search box that asks the site for matching districts as you type (other spellings such as Moneragala and Mahanuwara work too), or shows all 25 with the arrow, and works with the keyboard. Choosing a place in the Location box, or marking the map, fills it in while it is empty.
* Suggested name and short link under the title, made from the category, property type, bedrooms and district, for example "Bare land for sale in Galle" and /listing/bare-land-for-sale-galle/, with buttons to use them. A listing saved with an empty title gets them by itself, and so does an imported row without a title.

= 0.19.1 =
* Category carousel on phones: cards are 5:6 (a little taller than wide) with 24px padding at the top and bottom and 16px at the sides, and the dots are white for the dark section behind them.

= 0.19.0 =
* Category carousel on phones: one card at a time inside the container, with dots under it to show which card is on and to jump to a card. No arrows and no cards running off the edge on phones.
* Round arrows: no longer buttons, so Elementor's button styles can't change them. Background var(--e-global-color-3581a59), arrow var(--e-global-color-6e3d619), hover background var(--e-global-color-f5fd1b7), a little smaller while pressed, a focus ring for the keyboard (Enter and Space work), and faded when there is nothing more that way. They are shared, ready for other parts of the site.
* Cards have a 1px border of 20% white over the picture's edge, and the titles use var(--e-global-color-3581a59).

= 0.18.1 =
* Category carousel: the button always says View Properties.
* Listings → Widgets is smaller and simpler: each card is one line with its picture (click it to choose or change), the title and the link, plus small icons to move or remove it.

= 0.18.0 =
* Category carousel: [crc_category_carousel] shows a row of square cards, each with a picture, an H6 title and a View Properties button with an arrow (custom-btn-2-lite), with 16px between them. The cards have 16px corners, a 1px see-through border and 32px padding on computers, and the bottom of each is shaded so the words are easy to read. The picture zooms in slowly under the mouse.
* The cards line up with the container on the left and run on to the edge of the window on the right. The arrows sit over the container's edges and fade when there is nothing more that way. People can also swipe on phones or drag with the mouse; a thrown carousel lines up with the nearest card. Three cards across on computers, two on tablets, one and a bit on phones.
* Listings → Widgets: a new page with the carousel's shortcode and its cards: a picture from the Media Library, a title, the button text and the button link (the listing categories are offered). Cards can be added, removed and put in order by dragging or with Move up and Move down. Saving clears the LiteSpeed cache.

= 0.17.1 =
* Keyword ticker: stays exactly from edge to edge of the window when a scroll bar comes or goes, when its column is off to one side or has uneven padding, during Elementor's zoom-in animations, and on right-to-left language sites. Until its script runs it fills its container, so the page never scrolls sideways.
* Letters that reach below the line (g, p, y) are no longer cut off. Scrolling the page with a finger on the ticker no longer jerks it sideways, and pinch-zoom works over it.
* Saving the ticker settings clears the LiteSpeed page cache, so visitors see new keywords straight away, and its script isn't held back by page speed settings.
* Import keeps to 200 keywords of up to 100 letters and says so when a file has more; Settings says so too. A keyword with < & or quotes stays as typed.

= 0.17.0 =
* Keyword ticker: [crc_ticker] shows a row of keywords, such as Houses · Villas · Apartments, over the full width of the window. Each keyword is an H3 with no top or bottom margin, in var(--e-global-color-6e3d619), at the site's own H3 size, with a middle dot between them, centred with the words.
* It moves on its own and loops without a seam. It slows down gently and stops while the mouse is over it, or while it has keyboard focus. It can be dragged; let go while moving and it glides on, slowing down, before it carries on at its own speed. People who ask their device for less motion get a row that only moves when they drag it.
* Listings → Settings → Keyword ticker: the keywords (one a line), the speed in pixels a second, and the direction. Import from a JSON file and Export as a JSON file, with a sample file of 40 keywords. A page can have its own speed, direction and width with speed="…", direction="…" and width="container".

= 0.16.0 =
* FAQs: only one question is open at a time. The first one is open when the page loads, and opening another question closes the one that was open. Questions are padded 16px at the top and bottom (16px 24px).
* Listings → Import & Export: add or change many listings at once from a spreadsheet saved as CSV. Photos are downloaded from their links into the Media Library; Google Drive and Dropbox share links work. The import works a little at a time so the server never runs out of time, lists what happened to each row, and carries on where it stopped if the connection drops. New listings are added as drafts unless their status says publish, and a listing is only published once it has a main photo and a category.
* A sample file with three example listings shows every column, and the page explains what to put in each one.
* Export every listing as a CSV file in the same format, to keep a copy, or to change them in Excel or Google Sheets and import them back.
* Only one import runs at a time for each person, even with the page open in two windows, and a listing is never added twice when the connection drops. Rows with problems are always listed, with what to do about them, and an import left for a day is removed with the owner details in it.
* Changing listings from a file: only the filled-in cells change. For example, faq_2_answer changes only the answer of question 2, and a cell that can't be used is left out with a warning instead of erasing what is saved. Importing the same file again doesn't repeat features or details.
* Download listing, in the Publish box and under each listing in All Listings: a zip file with a page showing all the listing's details, its photos in full size, its spreadsheet row and all of its data. Editors and administrators also get the inquiries about it.
* Owner (private): a new box on the listing screen for the owner's first and last name, phone, email, address and special notes. They are only seen in the admin and in downloads, never on the website.
* Photos: phones write the exact place a photo was taken into the photo file. It is now removed when photos are uploaded or imported (JPEG, PNG, WebP, AVIF and HEIC), and once, a little at a time while the admin is used, from every photo already in the Media Library with all the sizes WordPress made of it. So nobody can find the exact place from a listing photo. Everything else in the photo stays as it was, including which way up it is.
* Import & Export: a new import can't replace one that isn't finished (continue it or stop it first). Stop works from any window, keeps the photos already downloaded, and says what it did. When the page is opened after an import ended, it shows what that import did.
* FAQs: a question the browser opens by itself, for example to show a word found with Ctrl+F, also closes the one that was open.
* The Info page is now called Shortcodes, and no longer shows the version or Check for updates (updates stay in Listings → Settings).

= 0.15.1 =
* Inquiry form: fields never show an outline while typing, whatever the theme or browser draws; only the site's Elementor field style shows.
* The phone box shows ordinary rectangular country flags (flag-icons) instead of round ones.
* The country list is now the form's own. It always opens under the phone box, and the page scrolls a little when the list doesn't fit on the screen. Each country shows its flag, name and code. The search box finds countries by name or code, for example "sri" or "+94". It also works with the keyboard (arrows, Enter and Escape). The chosen country is sent as before, and without JavaScript the browser's own list still works.

= 0.15.0 =
* FAQs: [crc_listing_faq] shows questions in cards with a 1px var(--e-global-color-f5fd1b7) border and 16px corners. Questions are padded 12px 24px in var(--e-global-color-6e3d619), and answers are in var(--e-global-color-text), 24px under the question. Answers open and close smoothly with the given chevron arrows (16px wide), and the first one is open at the start. open="none" or open="all" change that.
* Each listing category has its own questions, set on its Edit Listing Category screen and shown on every listing in it. Each listing can add its own in the new FAQs box, or leave the category's out. An answer can have an optional link under it, for example "Enquire about this land" going to the inquiry form (#crc-inquiry-1). The Listing Categories list shows how many questions each one has.
* The questions are given to search engines as FAQ structured data (schema.org FAQPage), once per page. schema="no" leaves this to an SEO plugin.
* A listing with no questions shows nothing, and the container with the class crc-listing-faq is hidden.
* Inquiry form: the phone box shows the chosen country's round flag (circle-flags), which follows the country list. Fields no longer get the browser's black outline while typing, or a red ring when something isn't right: only the site's Elementor field style shows, and the message under the field still says what to fix. The phone box uses the given chevron.

= 0.14.1 =
* Inquiry form: the fields and labels take the site's own Elementor styles (Site Settings → Form Fields and Typography); the form only lays them out. Fields use the page's font unless those styles give one. The country code in the phone box takes the fields' text style and starts where the text starts in the other fields.
* Required fields have a red star, after a space.
* The Email label is now E-Mail, and its messages say e-mail.
* The empty fields show Sri Lankan examples: e.g. Nimal, e.g. Perera, e.g. 077 123 4567, e.g. nimal.perera@gmail.com and an example message. The phone example follows the chosen country (20 countries besides Sri Lanka have one; others ask for the number in words), and the same example shows when a number isn't right.

= 0.14.0 =
* Inquiry form: [crc_listing_inquiry] shows the "Send an inquiry" form with First Name, Last Name, Phone Number, Email and Your Message. First name, phone number and email must be filled in. First and last name sit side by side on desktops, laptops and tablets, and one under the other on phones. The Send Inquiry button uses the site's custom-btn-3 style, full width, with an arrow that moves a little on hover.
* Phone numbers have a country code list with every country, with Sri Lanka chosen at first. Numbers are checked for the chosen country (077 123 4567, 77 123 4567 and +94 77 123 4567 all work), and a number typed with + chooses its own country.
* Emails are checked as they are typed, the site checks that the email's domain really exists, and typing mistakes in popular services are spotted ("Did you mean name@gmail.com?").
* Clear messages under each field say what to fix. The inquiry is sent without leaving the page, with a thank-you message by name.
* Spam protection: a hidden trap field, at most 5 inquiries from one visitor in 10 minutes, and hCaptcha's "I am human" box when it is turned on.
* Listings → Inquiries keeps every inquiry with buttons to reply by email, call or message on WhatsApp, so none are lost if an email doesn't arrive.
* Listings → Settings → Inquiry form: where inquiries are emailed, and hCaptcha with its site key and secret key, with a short guide. The secret key is never shown once saved.

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
