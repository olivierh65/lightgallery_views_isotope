// Version courte - Adaptée à la nouvelle structure Twig
function debugIsotope() {
  console.clear();
  console.log('🔍 ISOTOPE DEBUG - ' + new Date().toLocaleTimeString());

  // 1. Grilles d'albums (conteneurs Isotope principaux)
  const albumGrids = jQuery('.albums-grid[data-isotope-albums]');
  console.log(`🎞️ Grilles d'albums: ${albumGrids.length}`);

  albumGrids.each(function(i) {
    const $grid = jQuery(this);
    const iso = $grid.data('isotope');
    const id = this.id || `albums-grid-${i}`;

    console.group(`${iso ? '✅' : '❌'} ${id}`);
    console.log(`Instance Isotope: ${iso ? 'OUI' : 'NON'}`);
    console.log(`Dimensions grille: ${$grid.width()}×${$grid.height()}`);

    if (iso) {
      console.log(`Mode layout: ${iso.options.layoutMode}`);
      console.log(`Items trouvés: ${iso.items.length}`);
      console.log(`Sélecteur items: "${iso.options.itemSelector}"`);

      // Infos détaillées des items
      iso.items.forEach((item, idx) => {
        const pos = item.element.style.transform;
        console.log(`  Item ${idx}: pos="${pos}"`);
      });
    } else {
      const items = $grid.children('.album-item');
      console.warn(`Pas d'instance - Items dans le DOM: ${items.length}`);
    }

    console.groupEnd();
  });

  // 2. Conteneurs de groupes (imbriqués)
  const groupContainers = jQuery('.isotope-container[data-isotope-albums!="true"]');
  if (groupContainers.length > 0) {
    console.log(`\n📦 Conteneurs de groupes: ${groupContainers.length}`);

    groupContainers.each(function(i) {
      const $c = jQuery(this);
      const iso = $c.data('isotope');
      const id = this.id || `group-container-${i}`;
      const level = this.dataset.level || 'N/A';

      console.group(`${iso ? '✅' : '❌'} ${id} (Level: ${level})`);
      console.log(`Dimensions: ${$c.width()}×${$c.height()}`);

      if (iso) {
        console.log(`Mode: ${iso.options.layoutMode}`);
        console.log(`Items: ${iso.items.length}`);
      }

      console.groupEnd();
    });
  }

  // 3. Problèmes courants
  console.group('⚠️ PROBLÈMES DÉTECTÉS');

  let problemsFound = false;

  // Grilles d'albums sans instance
  albumGrids.each(function() {
    const $g = jQuery(this);
    if (!$g.data('isotope')) {
      console.error(`❌ Grille sans instance Isotope: #${this.id}`);
      problemsFound = true;
    }
    if ($g.width() < 100) {
      console.warn(`⚠️ Largeur faible (${$g.width()}px): #${this.id}`);
      problemsFound = true;
    }
    if ($g.height() === 0) {
      console.warn(`⚠️ Hauteur = 0: #${this.id}`);
      problemsFound = true;
    }

    const items = $g.children('.album-item');
    if (items.length === 0) {
      console.error(`❌ Aucun item .album-item trouvé dans #${this.id}`);
      problemsFound = true;
    }
  });

  if (!problemsFound) {
    console.log('✅ Aucun problème détecté');
  }

  console.groupEnd();

  // 4. Infos CSS
  console.group('🎨 CSS VÉRIFICATION');
  const sampleItem = jQuery('.album-item').first();
  if (sampleItem.length) {
    const styles = window.getComputedStyle(sampleItem[0]);
    console.log(`Position: ${styles.position}`);
    console.log(`Width: ${styles.width}`);
    console.log(`Height: ${styles.height}`);
    console.log(`Transform: ${styles.transform}`);
    console.log(`Display: ${styles.display}`);
  }
  console.groupEnd();
}

// Exécuter
debugIsotope();
