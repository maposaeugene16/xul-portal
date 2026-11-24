(() => {
  const side = document.getElementById('sidebar');
  const scrim = document.getElementById('scrim');
  const open = document.getElementById('openNav');
  const close = document.getElementById('closeNav');
  const closeNav = () => { side?.classList.remove('nav-open'); scrim?.classList.remove('visible'); };
  open?.addEventListener('click', () => { side?.classList.add('nav-open'); scrim?.classList.add('visible'); });
  close?.addEventListener('click', closeNav); scrim?.addEventListener('click', closeNav);
  const modal = document.getElementById('studentModal');
  const showModal = () => modal?.classList.add('open');
  const hideModal = () => modal?.classList.remove('open');
  document.getElementById('addStudentBtn')?.addEventListener('click', showModal);
  document.getElementById('cancelModal')?.addEventListener('click', hideModal);
  document.getElementById('closeModal')?.addEventListener('click', hideModal);
  modal?.addEventListener('click', e => { if (e.target === modal) hideModal(); });
  if (new URLSearchParams(location.search).get('add') === '1') showModal();
  document.getElementById('markAllPresent')?.addEventListener('click', () => {
    document.querySelectorAll('.attendance-status').forEach(select => { select.value = 'Present'; });
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { hideModal(); closeNav(); } });
})();
