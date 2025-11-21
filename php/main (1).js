// Mobile menu functionality
const menuBtn = document.getElementById('menuBtn');
const mainNav = document.getElementById('mainNav');
const closeMenu = document.getElementById('closeMenu');

if (menuBtn && mainNav && closeMenu) {
  menuBtn.addEventListener('click', () => {
    mainNav.classList.add('active');
  });

  closeMenu.addEventListener('click', () => {
    mainNav.classList.remove('active');
  });

  // Close menu when clicking on links
  const navLinks = document.querySelectorAll('.nav a');
  navLinks.forEach(link => {
    link.addEventListener('click', () => {
      mainNav.classList.remove('active');
    });
  });
}

// Dark mode toggle
const darkToggle = document.getElementById('darkToggle');

if (darkToggle) {
  darkToggle.addEventListener('click', () => {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    darkToggle.textContent = newTheme === 'dark' ? '☀️' : '🌙';
    
    // Save preference to localStorage
    localStorage.setItem('theme', newTheme);
  });

  // Check for saved theme preference
  const savedTheme = localStorage.getItem('theme');
  if (savedTheme) {
    document.documentElement.setAttribute('data-theme', savedTheme);
    darkToggle.textContent = savedTheme === 'dark' ? '☀️' : '🌙';
  }
}

// Active page indicator
function setActivePage() {
  const currentPage = window.location.pathname.split('/').pop() || 'index.php';
  const navLinks = document.querySelectorAll('.nav a');
  
  navLinks.forEach(link => {
    link.classList.remove('active');
    const linkPage = link.getAttribute('href');
    if (linkPage === currentPage || (currentPage === '' && linkPage === 'index.php')) {
      link.classList.add('active');
    }
  });
}

// Scroll effect for header
window.addEventListener('scroll', () => {
  const header = document.querySelector('.site-header');
  if (header) {
    if (window.scrollY > 50) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
  }
});

// Lightbox functionality for gallery images
function initLightbox() {
  const images = document.querySelectorAll('.masonry img, .grid img');
  
  images.forEach(img => {
    img.addEventListener('click', function() {
      openLightbox(this.src, this.alt);
    });
  });
}

function openLightbox(imageSrc, imageAlt) {
  const lightbox = document.createElement('div');
  lightbox.className = 'lightbox';
  lightbox.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    cursor: pointer;
  `;
  
  const img = document.createElement('img');
  img.src = imageSrc;
  img.alt = imageAlt;
  img.style.cssText = `
    max-width: 90%;
    max-height: 90%;
    border-radius: 10px;
    box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
    cursor: default;
  `;
  
  const closeBtn = document.createElement('button');
  closeBtn.textContent = '✕';
  closeBtn.style.cssText = `
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(0, 0, 0, 0.5);
    border: none;
    color: white;
    font-size: 2rem;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
  `;
  
  lightbox.appendChild(img);
  lightbox.appendChild(closeBtn);
  
  closeBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    lightbox.remove();
  });
  
  lightbox.addEventListener('click', (e) => {
    if (e.target === lightbox) {
      lightbox.remove();
    }
  });
  
  document.body.appendChild(lightbox);
}

// ========== BOOKING PAGE FUNCTIONALITY ==========
function initBookingPage() {
  const bookingForm = document.getElementById('bookingForm');
  const saveDraftBtn = document.getElementById('saveDraft');
  
  // Load draft if exists
  loadDraft();
  
  // Set minimum date to today
  const dateInput = document.getElementById('date');
  if (dateInput) {
    const today = new Date().toISOString().split('T')[0];
    dateInput.min = today;
  }
  
  // Handle URL parameters for pre-filled room selection
  const urlParams = new URLSearchParams(window.location.search);
  const roomParam = urlParams.get('room');
  if (roomParam && document.getElementById('room')) {
    const roomSelect = document.getElementById('room');
    for (let i = 0; i < roomSelect.options.length; i++) {
      if (roomSelect.options[i].text.includes(roomParam)) {
        roomSelect.selectedIndex = i;
        break;
      }
    }
  }
  
  // Save draft functionality
  if (saveDraftBtn) {
    saveDraftBtn.addEventListener('click', saveDraft);
  }
  
  // Form validation before PHP submission
  if (bookingForm) {
    bookingForm.addEventListener('submit', function(e) {
      if (!validateForm()) {
        e.preventDefault();
        showMessage('Please fill in all required fields correctly.', 'error');
        return false;
      }
      showMessage('Submitting booking...', 'info');
    });
  }
}

function saveDraft() {
  const formData = {
    name: document.getElementById('name').value,
    email: document.getElementById('email').value,
    phone: document.getElementById('phone').value,
    room: document.getElementById('room').value,
    date: document.getElementById('date').value,
    guests: document.getElementById('guests').value
  };
  
  localStorage.setItem('bookingDraft', JSON.stringify(formData));
  showMessage('Draft saved! You can continue later.', 'success');
}

function loadDraft() {
  const draft = localStorage.getItem('bookingDraft');
  if (draft) {
    const formData = JSON.parse(draft);
    Object.keys(formData).forEach(key => {
      const element = document.getElementById(key);
      if (element && formData[key]) {
        element.value = formData[key];
      }
    });
  }
}

function validateForm() {
  const name = document.getElementById('name').value;
  const email = document.getElementById('email').value;
  const phone = document.getElementById('phone').value;
  const date = document.getElementById('date').value;
  
  if (!name || !email || !phone || !date) {
    return false;
  }
  
  // Email validation
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(email)) {
    return false;
  }
  
  return true;
}

function showMessage(message, type = 'info') {
  const formMessage = document.getElementById('formMessage');
  if (formMessage) {
    formMessage.textContent = message;
    formMessage.className = type === 'success' ? 'success-message' : 'muted';
    formMessage.style.display = 'block';
    
    if (type === 'success') {
      setTimeout(() => {
        formMessage.style.display = 'none';
      }, 5000);
    }
  }
}

// ========== ROOMS PAGE MODAL FUNCTIONALITY ==========
function initRoomsPage() {
  const modal = document.getElementById('cottageModal');
  const modalImage = document.querySelector('.modal-image');
  const modalTitle = document.querySelector('.modal-title');
  const modalDescription = document.querySelector('.modal-description');
  const modalCapacity = document.querySelector('.modal-capacity');
  const modalPrice = document.querySelector('.modal-price');
  const modalBestFor = document.querySelector('.modal-bestfor');
  const modalLocation = document.querySelector('.modal-location');
  const modalAmenitiesList = document.querySelector('.modal-amenities-list');
  const closeModalBtn = document.querySelector('.close-modal');
  const bookNowBtn = document.querySelector('.book-now-btn');
  const modalTriggers = document.querySelectorAll('.image-modal-trigger');

  // Cottage data for the modal - COMPLETE VERSION
  const cottageData = {
    "WHITE HOUSE": {
      image: "https://images.unsplash.com/photo-1582268611958-ebfd161ef9cf?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "A luxurious beachfront cottage with panoramic ocean views and premium amenities.",
      capacity: "Up to 18-25 guests",
      price: "₱30,000",
      bestFor: "Large families, reunions, barkada",
      location: "Beachfront",
      amenities: ["Private balcony", "Ocean view", "King-size bed", "Hot & cold shower", "Pool access", "Air conditioning", "Kitchenette", "Free WiFi"]
    },
    "PENTHOUSE": {
      image: "https://images.unsplash.com/photo-1564078516393-cf04bd966897?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Our most exclusive accommodation with 360-degree views and premium luxury features.",
      capacity: "Up to 12-15 guests",
      price: "₱12,800",
      bestFor: "Barkada, small events, luxury travelers",
      location: "Top floor",
      amenities: ["Ocean view", "Private terrace", "Hot & cold shower", "Air conditioning", "Free WiFi", "Pool access"]
    },
    "AQUA CLASS": {
      image: "https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Modern cottage with direct pool access and contemporary design elements.",
      capacity: "Up to 12-15 guests",
      price: "₱11,800",
      bestFor: "Families, groups",
      location: "Poolside",
      amenities: ["Direct pool access", "Air conditioning", " Lounge area", "Free WiFi", "Smart TV"]
    },
    "HEARTSUITE": {
      image: "https://images.unsplash.com/photo-1611892440504-42a792e24d32?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Romantic suite designed for couples with special touches and intimate atmosphere.",
      capacity: "Up to 12-15 guests",
      price: "₱11,800",
      bestFor: "Friends, group",
      location: "Garden view",
      amenities: ["Air conditioning", "King-size bed", "Lounge area", "Free WiFi", "Mini fridge", "Pool access"]
    },
    "STEPH'S SKYLOUNGE 842/844": {
      image: "https://images.unsplash.com/photo-1590490360182-c33d57733427?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Spacious interconnected rooms perfect for families or groups traveling together.",
      capacity: "Up to 10-12 guests",
      price: "₱11,800",
      bestFor: "Families, groups",
      location: "Upper floor with ocean view",
      amenities: ["Wide window view", "Air conditioning", "Hot & cold shower", "Free WiFi", "Dining area", "Pool access"]
    },
    "STEPH'S 846": {
      image: "https://images.unsplash.com/photo-1578683010236-d716f9a3f461?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Comfortable cottage with modern amenities and convenient access to resort facilities.",
      capacity: "Up to 8-10 guests",
      price: "₱10,000",
      bestFor: "Solo travelers, small group",
      location: "Central resort area",
      amenities: ["Queen-size bed", "Air conditioning", "Hot & cold shower", "Free WiFi", "Dining area", "Pool access"]
    },
    "STEPH'S 848": {
      image: "https://images.unsplash.com/photo-1566665797739-1674de7a421a?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Cozy cottage with garden views and comfortable furnishings for a relaxing stay.",
      capacity: "Up to 8-10 guests",
      price: "₱10,800",
      bestFor: "Solo travelers, small groups",
      location: "Main resort",
      amenities: ["Queen-size bed", "Air conditioning", "Hot & cold shower", "Free WiFi", "Dining area", "Pool access"]
    },
    "DE LUXE": {
      image: "https://images.unsplash.com/photo-1631049307264-da0ec9d70304?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Premium accommodation with upgraded amenities and stylish interior design.",
      capacity: "Up to 8-10 guests",
      price: "₱8,800",
      bestFor: "friends, small families",
      location: "Beachfront",
      amenities: ["Air conditioning", "Hot & cold shower", "Free WiFi", "Dining area", "Pool access", "Premium bedding"]
    },
    "BEATRICE A": {
      image: "https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Charming cottage with traditional design elements and modern comforts.",
      capacity: "Up to 6-8 guests",
      price: "₱7,800",
      bestFor: "Couples, small families",
      location: "Ground floor",
      amenities: ["Air conditioning", "Hot & cold shower", "Free WiFi", "Dining area", "Pool access", "Premium bedding"]
    },
    "BEATRICE B": {
      image: "https://images.unsplash.com/photo-1564501049412-61c2a3083791?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Comfortable cottage with garden access and relaxing atmosphere.",
      capacity: "Up to 6-8 guests",
      price: "₱6,800",
      bestFor: "Travelers, small families",
      location: "Ground floor",
      amenities: ["Air conditioning", "Hot & cold shower", "Free WiFi", "Dining area", "Pool access", "Premium bedding"]
    },
    "CONCIERGE 815/819": {
      image: "https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Interconnected rooms with premium concierge service and exclusive amenities.",
      capacity: "Up to 6-8 guests",
      price: "₱8,800",
      bestFor: "Families, groups",
      location: "Main building",
      amenities: ["Spacious layout", "Air conditioning", "Work desk", "Pool access", "Free WiFi"]
    },
    "CONCIERGE 817": {
      image: "https://images.unsplash.com/photo-1591088398332-8a7791972843?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Premium room with dedicated concierge service and business-friendly amenities.",
      capacity: "Up to 8-10 guests",
      price: "₱9,800",
      bestFor: "Families, barkada",
      location: "Main building",
      amenities: ["Lounge area", "Air conditioning", "Free WiFi", "Pool access", "Mini fridge"]
    },
    "PREMIUM 838": {
      image: "https://images.unsplash.com/photo-1578662996442-48f60103fc96?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Upgraded accommodation with premium features and stylish design.",
      capacity: "Up to 6-8 guests",
      price: "₱7,800",
      bestFor: "Couples, small groups",
      location: "Beach view",
      amenities: ["Beach view", "King-size bed", "Air conditioning", " Free WiFi", "Pool access"]
    },
    "PREMIUM 840": {
      image: "https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Spacious premium cottage with modern amenities and comfortable living space.",
      capacity: "Up to 6-8 guests",
      price: "₱8,800",
      bestFor: "Couples, small families",
      location: "Beach view",
      amenities: [" Air conditioning", "King-size bed", "Sitting area", "Private bathroom", "Free WiFi", "Pool access"]
    },
    "GIANT KUBO": {
      image: "https://images.unsplash.com/photo-1586375300773-8384e3e4916f?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Traditional Filipino-style cottage with modern amenities and spacious layout.",
      capacity: "Up to 12-15 guests",
      price: "₱6,800",
      bestFor: "Large families, groups",
      location: "Garden area",
      amenities: ["Native bamboo design", "Open-air concept", "Multiple beds", "Electric fan", " Pool access"]
    },
    "SEASIDE (WHOLE)": {
      image: "https://images.unsplash.com/photo-1512918728675-ed5a9ecdebfd?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Complete seaside cottage with direct beach access and panoramic ocean views.",
      capacity: "Up to 14-16 guests",
      price: "₱6,800",
      bestFor: "Families, groups",
      location: "Beachfront",
      amenities: ["Seaside cottage", "Oceanfront", "Spacious seating", " Pool access"]
    },
    "SEASIDE (HALF)": {
      image: "https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Cozy seaside accommodation with beach proximity and comfortable amenities.",
      capacity: "Up to 8-10 guests",
      price: "₱3,400",
      bestFor: "Couples, small families",
      location: "Beachfront",
      amenities: ["Open-style cottage", "Oceanfront", "Queen-size bed", "Kitchenette", "Pool access"]
    },
    "BAMBOO KUBO": {
      image: "https://images.unsplash.com/photo-1552733407-5d5c46c3bb3b?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Authentic bamboo cottage offering a traditional Filipino experience with modern comforts.",
      capacity: "Up to 6-8 guests",
      price: "₱2,800",
      bestFor: "Budget groups",
      location: "Beachfront area",
      amenities: ["Bamboo structure", "Queen-size bed", "Pool access", "Ceiling fan", "Mini fridge"]
    }
  };

  // Open modal when cottage card is clicked
  modalTriggers.forEach(trigger => {
    trigger.addEventListener('click', function() {
      const cottageName = this.getAttribute('data-cottage');
      const cottage = cottageData[cottageName];
      
      if (cottage) {
        modalImage.src = cottage.image;
        modalImage.alt = cottageName;
        modalTitle.textContent = cottageName;
        modalDescription.textContent = cottage.description;
        modalCapacity.textContent = cottage.capacity;
        modalPrice.textContent = cottage.price;
        modalBestFor.textContent = cottage.bestFor;
        modalLocation.textContent = cottage.location;
        
        // Clear and populate amenities list
        modalAmenitiesList.innerHTML = '';
        cottage.amenities.forEach(amenity => {
          const li = document.createElement('li');
          li.textContent = amenity;
          modalAmenitiesList.appendChild(li);
        });
        
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
      }
    });
  });

  // Close modal
  closeModalBtn.addEventListener('click', function() {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
  });

  // Close modal when clicking outside
  window.addEventListener('click', function(event) {
    if (event.target === modal) {
      modal.style.display = 'none';
      document.body.style.overflow = 'auto';
    }
  });

  // Book now button in modal
  if (bookNowBtn) {
    bookNowBtn.addEventListener('click', function() {
      const cottageName = modalTitle.textContent;
      window.location.href = 'booking.php?room=' + encodeURIComponent(cottageName);
    });
  }
}

// Promo slide functionality
const closePromo = document.getElementById('closePromo');
const promoSlide = document.getElementById('promoSlide');

if (closePromo && promoSlide) {
  closePromo.addEventListener('click', () => {
    promoSlide.classList.add('hidden');
    localStorage.setItem('promoClosed', 'true');
  });

  // Check if promo was previously closed
  if (localStorage.getItem('promoClosed') === 'true') {
    promoSlide.classList.add('hidden');
  }
}

// Page-specific initialization
function initPageSpecificFeatures() {
  const currentPage = window.location.pathname.split('/').pop();
  
  if (currentPage === 'booking.php') {
    initBookingPage();
  }
  
  if (currentPage === 'gallery.php') {
    initLightbox();
  }
  
  if (currentPage === 'rooms.php') {
    initRoomsPage();
  }
}

// Initialize everything when page loads
document.addEventListener('DOMContentLoaded', function() {
  setActivePage();
  initPageSpecificFeatures();
  
  console.log('🏝️ Heart Of D\' Ocean Beach Resort website loaded successfully!');
});