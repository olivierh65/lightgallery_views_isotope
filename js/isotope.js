(function ($, Drupal, drupalSettings, once) {
  var isoInstances = {}; // Store multiple Isotope instances

  Drupal.behaviors.lightgalleryAlbums = {
    attach: function (context, settings) {
      console.log("lightgalleryAlbums Packery behavior attached");
      const galleryOptions = drupalSettings.settings?.layout || {
        rowHeight: "",
        maxRowHeight: "",
        gutter: 5,
        layout: "packery",
      };

      // Initialize Isotope for each group gallery
      $(
        $(once("albums-gallery-init", ".albums-gallery", context))
          .get()
          .reverse()
      ).each(function () {
        const $gallery = $(this);
        const galleryId = $gallery.attr("id");

        if (!galleryId) {
          return; // Must have a unique ID
        }

        // Prevent duplicate initializations (double safety)
        if (isoInstances[galleryId]) {
          return;
        }

        // Initialize Isotope instance
        const iso = $gallery.isotope({
          itemSelector: ".album-block",
          initLayout: true,
          layoutMode: "packery",

          masonry: {
            gutter: parseInt(galleryOptions.gutter) || 5,
            columnWidth: parseInt(galleryOptions.columnWidth) || "",
            horizontalOrder: galleryOptions.horizontalOrder || false,
            fitWidth: galleryOptions.fitWidth || false,
            percentPosition: true,
          },

          packery: {
            gutter: parseInt(galleryOptions.gutter) || 5,
            columnWidth: parseInt(galleryOptions.columnWidth) || "",
            rowHeight: galleryOptions.rowHeight || "",
            horizontalOrder: galleryOptions.horizontalOrder || false,
            percentPosition: true,
          },

          fitRows: {
            gutter: parseInt(galleryOptions.gutter) || 5,
          },

          vertical: {
            gutter: parseInt(galleryOptions.gutter) || 5,
            horizontalOrder: galleryOptions.horizontalOrder || false,
          },
        });

        // Save instance
        isoInstances[galleryId] = iso.data("isotope");

        // Proper layout when images are fully loaded
        $gallery.imagesLoaded(function () {
          iso.isotope("layout");
        });
      });

      // Final relayout once everything is on screen
      $(window).one("load", function () {
        Object.values(isoInstances).forEach(function (instance) {
          instance.layout();
        });
      });

      // Attach click handlers to album covers
      $(once("lg-album", ".album-cover", context)).on("click", function (e) {
        e.preventDefault();
        var albumId = $(this).data("album-id");
        var $album = $("#" + albumId);

        // Get album-specific settings
        var albumSettings =
          drupalSettings.lightgallery?.albums?.[albumId] || {};

        if ($album.length && typeof window.lightGallery === "function") {
          if (!$album.data("lightGallery")) {
            // Extract plugin names
            const plugins = [];
            const pluginNames = Object.values(albumSettings.plugins || {});
            pluginNames.forEach((name) => {
              if (window[name]) {
                plugins.push(window[name]);
              }
            });

            // Initialize lightGallery
            const instance = window.lightGallery($album[0], {
              ...albumSettings,
              selector: "a",
              plugins: plugins,
              subHtmlSelectorRelative: true,
            });
            $album.data("lightGallery", instance);
          }
          // Open the gallery
          $album.data("lightGallery").openGallery();
        } else {
          console.error("LightGallery is not loaded!");
        }
      });
    },
  };
})(jQuery, Drupal, drupalSettings, window.once);
