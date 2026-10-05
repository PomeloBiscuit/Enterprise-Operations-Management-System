(function () {
  'use strict';

  var root = document.documentElement;
  var isEnglish = root.lang === 'en';
  var toggle = document.getElementById('eoms-theme-toggle');
  var languageLink = document.getElementById('eoms-language-toggle');
  var diagrams = Array.from(document.querySelectorAll('.mermaid'));
  var images = Array.from(document.querySelectorAll('img[data-src-dark]'));
  var renderQueue = Promise.resolve();

  if (!toggle || !languageLink) return;
  images.forEach(function (img) { img.dataset.srcLight = img.getAttribute('src'); });
  diagrams.forEach(function (diagram) { diagram.dataset.source = diagram.textContent.trim(); });

  function updateImages() {
    var dark = root.dataset.theme === 'dark';
    images.forEach(function (img) {
      var path = dark ? img.dataset.srcDark : img.dataset.srcLight;
      img.setAttribute('src', path);
      var link = img.closest('.figure-scroll a');
      if (link) link.setAttribute('href', path);
    });
  }

  function updateToggle() {
    var dark = root.dataset.theme === 'dark';
    toggle.setAttribute('aria-pressed', String(dark));
    toggle.setAttribute('aria-label', isEnglish ? (dark ? 'Switch to light theme' : 'Switch to dark theme') : (dark ? '切換至亮色模式' : '切換至深色模式'));
    toggle.textContent = isEnglish ? (dark ? 'Light mode' : 'Dark mode') : (dark ? '亮色模式' : '深色模式');
  }

  function renderDiagrams() {
    if (typeof mermaid === 'undefined') return Promise.resolve();
    renderQueue = renderQueue.catch(function () {}).then(function () {
      diagrams.forEach(function (diagram) { diagram.textContent = diagram.dataset.source; diagram.removeAttribute('data-processed'); });
      mermaid.initialize({ startOnLoad: false, theme: root.dataset.theme === 'dark' ? 'dark' : 'default', fontFamily: getComputedStyle(document.body).fontFamily, securityLevel: 'strict', flowchart: { htmlLabels: true, useMaxWidth: true }, er: { useMaxWidth: true } });
      return mermaid.run({ querySelector: '.mermaid' });
    });
    return renderQueue;
  }

  languageLink.addEventListener('click', function (event) {
    event.preventDefault();
    var destination = new URL(languageLink.getAttribute('href'), location.href);
    destination.hash = location.hash;
    location.assign(destination.href);
  });
  toggle.addEventListener('click', function () {
    root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem('eoms-pages-theme', root.dataset.theme); } catch (error) { /* Storage may be unavailable. */ }
    updateToggle(); updateImages(); renderDiagrams();
  });

  updateToggle(); updateImages();
  if (typeof mermaid === 'undefined') {
    diagrams.forEach(function (diagram) { var pre = document.createElement('pre'); pre.textContent = diagram.dataset.source; diagram.replaceWith(pre); });
    return;
  }
  renderDiagrams();
}());
