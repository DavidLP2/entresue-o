(() => {
  const announcementContainer = document.getElementById('collection-announcement');
  if (!announcementContainer) return;

  fetch('api/anuncio.php', { headers: { Accept: 'application/json' }, cache: 'no-store' })
    .then(response => {
      if (!response.ok) {
        throw new Error(`El servidor respondió con estado ${response.status}.`);
      }
      return response.json();
    })
    .then(data => {
      const announcement = data.announcement;
      if (!announcement || !announcement.title || !announcement.message) return;

      document.getElementById('announcement-title').textContent = announcement.title;
      document.getElementById('announcement-message').textContent = announcement.message;
      announcementContainer.hidden = false;
    })
    .catch(error => {
      console.error('No se pudo cargar el anuncio de D&L Pijamas:', error);
    });
})();
