(function ($, Drupal, drupalSettings, once) {
  var iso;
  Drupal.behaviors.lightgalleryAlbums = {
    attach: function (context, settings) {
      console.log("lightgalleryAlbums Packery behavior attached");
      const galleryOptions = drupalSettings.settings?.layout || {
        rowHeight: "",
        maxRowHeight: "",
        gutter: 5,
        layout: "packery",
      };

      $(window).one("load", function () {
        iso.isotope("layout");
      });

      iso = $("#albums-gallery").isotope({
        itemSelector: ".album-block",
        // width: galleryOptions.width || "30%",
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

      $(once("lg-album", ".album-cover", context)).on("click", function (e) {
        e.preventDefault();
        var albumId = $(this).data("album-id");
        var $album = $("#" + albumId);

        // Récupère les settings spécifiques à cet album
        var albumSettings =
          drupalSettings.lightgallery?.albums?.[albumId] || {};

        if ($album.length && typeof window.lightGallery === "function") {
          if (!$album.data("lightGallery")) {
            // Initialisation native
            const plugins = [];
            // Extract values from plugins object
            const pluginNames = Object.values(albumSettings.plugins || {});
            pluginNames.forEach((name) => {
              if (window[name]) {
                plugins.push(window[name]);
              }
            });

            const instance = window.lightGallery($album[0], {
              ...albumSettings,
              selector: "a",
              plugins: plugins,
              subHtmlSelectorRelative: true,
              // autres options...
            });
            $album.data("lightGallery", instance);
          }
          // Ouvre la galerie
          $album.data("lightGallery").openGallery();
        } else {
          console.error("LightGallery is not loaded!");
        }
      });
    },
  };
})(jQuery, Drupal, drupalSettings, window.once);
