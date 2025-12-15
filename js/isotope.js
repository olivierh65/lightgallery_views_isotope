(function ($, Drupal, drupalSettings, once) {

  /**
   * Album Gallery Behavior - CSS Grid Layout with LightGallery
   * 
   * No Isotope needed - CSS Grid handles all responsive layout automatically.
   * LightGallery provides the lightbox functionality.
   */

  Drupal.behaviors.lightgalleryAlbums = {
    attach: function (context, settings) {
      console.log("=== Album Gallery (CSS Grid + LightGallery) ===");

      // Initialize LightGallery on album covers
      $(once("lg-album-init", ".album-cover", context)).each(function () {
        $(this).on("click", function (e) {
          e.preventDefault();

          const albumId = $(this).data("album-id");
          const $album = $("#" + albumId);

          if (!$album.length) {
            console.warn("Album container not found:", albumId);
            return;
          }

          if (typeof window.lightGallery !== "function") {
            console.error("LightGallery is not loaded!");
            return;
          }

          const albumSettings = drupalSettings.settings.lightgallery?.albums?.[albumId] || {};

          // Initialize LightGallery if not already done
          if (!$album.data("lightGallery")) {
            const plugins = [];
            Object.values(albumSettings.plugins || {}).forEach((name) => {
              if (window[name]) plugins.push(window[name]);
            });

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
        });
      });

      console.log("✓ Album gallery initialized (CSS Grid + LightGallery)");
    },
  };

})(jQuery, Drupal, drupalSettings, window.once);

