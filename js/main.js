/* ===== AREEJ FURNITURE — main.js ===== */

(function () {
  'use strict';

  /* ---------- HEADER SCROLL ---------- */
  const header = document.getElementById('header');
  if (header) {
    window.addEventListener('scroll', () => {
      header.classList.toggle('scrolled', window.scrollY > 60);
    }, { passive: true });
  }

  /* ---------- HAMBURGER MENU ---------- */
  const hamburger = document.getElementById('hamburger');
  const nav = document.getElementById('nav');
  if (hamburger && nav) {
    hamburger.addEventListener('click', () => {
      hamburger.classList.toggle('open');
      nav.classList.toggle('open');
    });
    nav.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', () => {
        hamburger.classList.remove('open');
        nav.classList.remove('open');
      });
    });
  }

  /* ---------- ACTIVE NAV LINK (scroll spy) ---------- */
  function updateActiveNav() {
    const sections = document.querySelectorAll('section[id]');
    const scrollY = window.scrollY + 120;
    sections.forEach(section => {
      const top = section.offsetTop;
      const height = section.offsetHeight;
      const id = section.getAttribute('id');
      const link = document.querySelector(`.nav-link[href="#${id}"]`);
      if (link) link.classList.toggle('active', scrollY >= top && scrollY < top + height);
    });
  }
  window.addEventListener('scroll', updateActiveNav, { passive: true });

  /* ---------- SCROLL ANIMATIONS ---------- */
  const animatedEls = document.querySelectorAll('.animate-fade-up, .animate-fade-in, .animate-scale');
  if (animatedEls.length) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    animatedEls.forEach(el => observer.observe(el));
  }

  /* ---------- COUNTER ANIMATION ---------- */
  function animateCounter(el) {
    const target = parseInt(el.dataset.target, 10);
    const duration = 1800;
    const step = target / (duration / 16);
    let current = 0;
    const timer = setInterval(() => {
      current = Math.min(current + step, target);
      el.textContent = Math.floor(current).toLocaleString('ar-SA');
      if (current >= target) {
        el.textContent = target.toLocaleString('ar-SA');
        clearInterval(timer);
      }
    }, 16);
  }

  const counterEls = document.querySelectorAll('.stat-number[data-target]');
  if (counterEls.length) {
    const counterObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          counterObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });
    counterEls.forEach(el => counterObserver.observe(el));
  }

  /* ---------- PARTICLES (Hero) ---------- */
  const particlesContainer = document.getElementById('particles');
  if (particlesContainer) {
    const count = 18;
    for (let i = 0; i < count; i++) {
      const p = document.createElement('div');
      p.className = 'particle';
      const size = Math.random() * 10 + 4;
      const x = Math.random() * 100;
      const dur = Math.random() * 12 + 8;
      const delay = Math.random() * 10;
      p.style.cssText = `
        width:${size}px; height:${size}px;
        left:${x}%;
        animation-duration:${dur}s;
        animation-delay:${delay}s;
        opacity:${Math.random() * 0.3 + 0.05};
      `;
      particlesContainer.appendChild(p);
    }
  }

  /* ---------- LIGHTBOX ---------- */
  let currentImages = [];
  let currentIndex = 0;

  const lightbox = document.getElementById('lightbox');
  const lightboxImg = document.getElementById('lightboxImg');

  function openLightbox(images, index) {
    if (!lightbox || !lightboxImg) return;
    currentImages = images;
    currentIndex = index;
    lightboxImg.src = currentImages[currentIndex];
    lightbox.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeLightbox() {
    if (!lightbox) return;
    lightbox.classList.remove('active');
    document.body.style.overflow = '';
    setTimeout(() => { if (lightboxImg) lightboxImg.src = ''; }, 300);
  }

  function prevImage() {
    currentIndex = (currentIndex - 1 + currentImages.length) % currentImages.length;
    lightboxImg.src = currentImages[currentIndex];
  }

  function nextImage() {
    currentIndex = (currentIndex + 1) % currentImages.length;
    lightboxImg.src = currentImages[currentIndex];
  }

  if (lightbox) {
    document.getElementById('lightboxClose')?.addEventListener('click', closeLightbox);
    document.getElementById('lightboxPrev')?.addEventListener('click', prevImage);
    document.getElementById('lightboxNext')?.addEventListener('click', nextImage);
    lightbox.addEventListener('click', (e) => { if (e.target === lightbox) closeLightbox(); });
    document.addEventListener('keydown', (e) => {
      if (!lightbox.classList.contains('active')) return;
      if (e.key === 'Escape') closeLightbox();
      if (e.key === 'ArrowRight') prevImage();
      if (e.key === 'ArrowLeft') nextImage();
    });
  }

  /* Gallery items — collect images and bind click */
  function bindGallery(selector) {
    const items = document.querySelectorAll(selector);
    if (!items.length) return;
    const images = Array.from(items).map(item => item.querySelector('img')?.src).filter(Boolean);
    items.forEach((item, idx) => {
      item.addEventListener('click', () => openLightbox(images, idx));
    });
  }

  bindGallery('.gallery-item');
  bindGallery('.page-gallery-item');

  /* Expose bindGallery globally for dynamic gallery loading */
  window.bindGallery = bindGallery;

  /* ---------- SMOOTH SCROLL for anchor links ---------- */
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', (e) => {
      const target = document.querySelector(anchor.getAttribute('href'));
      if (target) {
        e.preventDefault();
        const offset = 80;
        window.scrollTo({ top: target.offsetTop - offset, behavior: 'smooth' });
      }
    });
  });

})();
