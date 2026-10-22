document.addEventListener('DOMContentLoaded', () => {
  const sidebar=document.getElementById('sidebar');
  const backdrop=document.getElementById('sidebar-backdrop');
  const openBtn=document.getElementById('openSidebar');
  const closeBtn=document.getElementById('closeSidebar');
  let priorFocus=null;
  function openSidebar(){priorFocus=document.activeElement;document.body.classList.add('sidebar-open');backdrop.hidden=false;openBtn?.setAttribute('aria-expanded','true');closeBtn?.focus();}
  function closeSidebar(){document.body.classList.remove('sidebar-open');backdrop.hidden=true;openBtn?.setAttribute('aria-expanded','false');if(priorFocus?.focus)priorFocus.focus();}
  openBtn?.addEventListener('click',openSidebar);
  closeBtn?.addEventListener('click',closeSidebar);
  backdrop?.addEventListener('click',closeSidebar);
  document.addEventListener('keydown',e=>{if(e.key==='Escape' && document.body.classList.contains('sidebar-open'))closeSidebar();});
  sidebar?.querySelectorAll('nav a').forEach(a=>a.addEventListener('click',()=>{if(window.innerWidth<=850)closeSidebar();}));
  window.addEventListener('resize',()=>{if(window.innerWidth>850 && document.body.classList.contains('sidebar-open'))closeSidebar();});
  // Desktop-only collapsible navigation, independent of the mobile drawer.
  const desktopToggle=document.getElementById('toggleDesktopSidebar');
  const desktopMedia=window.matchMedia('(min-width: 851px)');
  function setDesktopCollapsed(collapsed){
    document.body.classList.toggle('sidebar-collapsed',collapsed);
    desktopToggle?.setAttribute('aria-expanded',String(!collapsed));
    desktopToggle?.setAttribute('aria-label',collapsed?'Expandir menú':'Contraer menú');
    desktopToggle?.setAttribute('title',collapsed?'Expandir menú':'Contraer menú');
  }
  try {setDesktopCollapsed(localStorage.getItem('mt2-sidebar-collapsed')==='1');}
  catch(_){setDesktopCollapsed(false);}
  desktopToggle?.addEventListener('click',()=>{
    if(!desktopMedia.matches)return;
    const next=!document.body.classList.contains('sidebar-collapsed');
    setDesktopCollapsed(next);
    try{localStorage.setItem('mt2-sidebar-collapsed',next?'1':'0');}catch(_){}
  });
  sidebar?.querySelectorAll('.side-nav a').forEach(link=>{
    const label=link.querySelector('.nav-label')?.textContent?.trim();
    if(label)link.title=label;
  });
  const backToTop=document.getElementById('backToTop');
  const refreshBackToTop=()=>{if(backToTop)backToTop.hidden=window.scrollY<260;};
  window.addEventListener('scroll',refreshBackToTop,{passive:true});
  backToTop?.addEventListener('click',()=>window.scrollTo({top:0,behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'}));
  refreshBackToTop();

  // ==========================================
  // MT 2.0 - Tema visual por usuario
  // ==========================================

  const themeButton = document.getElementById('theme');
  let savedTheme = document.body.dataset.mt2Theme || 'dark';

  const systemTheme = window.matchMedia(
    '(prefers-color-scheme: dark)'
  );

  function applyTheme(theme) {
    const isLight = theme === 'light'
      || (theme === 'auto' && !systemTheme.matches);

    document.body.classList.toggle('light', isLight);
    document.body.dataset.mt2Theme = theme;
  }

  // Aplicar la preferencia guardada en PostgreSQL.
  applyTheme(savedTheme);

  // Si el usuario seleccionó "Automático",
  // reaccionar ante cambios del sistema operativo.
  systemTheme.addEventListener('change', () => {
    if (document.body.dataset.mt2Theme === 'auto') {
      applyTheme('auto');
    }
  });

  // Cambio rápido de tema desde el encabezado.
  themeButton?.addEventListener('click', async () => {
    const current = document.body.classList.contains('light')
      ? 'light'
      : 'dark';

    const next = current === 'light' ? 'dark' : 'light';

    // Aplicación inmediata para evitar esperas visuales.
    applyTheme(next);

    try {
      const response = await fetch('/mt2/configuracion/tema', {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector(
            'meta[name="csrf-token"]'
          )?.content || ''
        },
        body: JSON.stringify({ theme: next })
      });

      if (!response.ok) {
        throw new Error('No se pudo guardar el tema');
      }
      savedTheme = next;

    } catch (error) {
      console.error(error);

      // Revertir si el servidor no pudo guardar.
      applyTheme(savedTheme);

      alert('No se pudo guardar la preferencia de apariencia.');
    }
  });

});
