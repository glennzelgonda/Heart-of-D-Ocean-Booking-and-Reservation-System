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
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  const navLinks = document.querySelectorAll('.nav a');
  
  navLinks.forEach(link => {
    link.classList.remove('active');
    const linkPage = link.getAttribute('href');
    if (linkPage === currentPage || (currentPage === '' && linkPage === 'index.html')) {
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
// ========== GCASH PAYMENT INTEGRATION ==========
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
  
  // Form submission - DIRECT TO GCASH
  if (bookingForm) {
    bookingForm.addEventListener('submit', function(e) {
      e.preventDefault();
      // DIRECT TO GCASH AGAD
      processBookingAndGCash();
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

// DIRECT PROCESS TO GCASH
function processBookingAndGCash() {
  // Validate form first
  if (!validateForm()) {
    showMessage('Please fill in all required fields.', 'error');
    return;
  }
  
  const formData = {
    name: document.getElementById('name').value,
    email: document.getElementById('email').value,
    phone: document.getElementById('phone').value,
    room: document.getElementById('room').value,
    date: document.getElementById('date').value,
    guests: document.getElementById('guests').value,
    timestamp: new Date().toISOString(),
    bookingId: 'RESORT' + Date.now()
  };
  
  // Save booking to localStorage
  const bookings = JSON.parse(localStorage.getItem('bookings') || '[]');
  bookings.push(formData);
  localStorage.setItem('bookings', JSON.stringify(bookings));
  
  // Clear draft
  localStorage.removeItem('bookingDraft');
  
  // DIRECT TO GCASH - NO MESSAGE, NO DELAY
  openGCashPayment(formData);
}

function validateForm() {
  const name = document.getElementById('name').value;
  const email = document.getElementById('email').value;
  const phone = document.getElementById('phone').value;
  const date = document.getElementById('date').value;
  
  if (!name || !email || !phone || !date) {
    return false;
  }
  
  return true;
}

function openGCashPayment(bookingData) {
  const amount = calculateGCashAmount(bookingData.room, bookingData.guests);
  
  // Open GCash window immediately
  const gcashWindow = window.open('', 'GCash Payment', 'width=500,height=700,scrollbars=yes');
  
  gcashWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>GCash Payment - Heart Of D' Ocean Beach Resort</title>
      <style>
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
        }
        body { 
          font-family: 'Arial', sans-serif; 
          padding: 20px; 
          text-align: center;
          background: linear-gradient(135deg, #0033A0, #0070BA);
          min-height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        .gcash-container {
          background: white;
          padding: 30px;
          border-radius: 20px;
          box-shadow: 0 10px 30px rgba(0,0,0,0.3);
          max-width: 400px;
          width: 100%;
        }
        .gcash-logo { 
          color: #0033A0; 
          font-size: 2.5em; 
          font-weight: bold;
          margin-bottom: 20px;
        }
        .amount { 
          font-size: 3em; 
          color: #0033A0; 
          margin: 20px 0;
          font-weight: bold;
        }
        .details {
          text-align: left;
          margin: 25px 0;
          padding: 20px;
          background: #f8f9fa;
          border-radius: 12px;
          border-left: 4px solid #0033A0;
        }
        .details p {
          margin: 8px 0;
          color: #333;
        }
        .qr-container {
          background: #fff;
          padding: 25px;
          border: 3px dashed #0033A0;
          border-radius: 15px;
          margin: 20px 0;
        }
        .qr-placeholder {
          font-size: 4em;
          margin: 10px 0;
        }
        .btn { 
          background: #0033A0; 
          color: white; 
          padding: 15px 30px; 
          border: none; 
          border-radius: 25px; 
          font-size: 1.1em; 
          cursor: pointer;
          margin: 10px;
          font-weight: bold;
          width: 200px;
        }
        .btn.success { 
          background: #28a745; 
        }
        .btn.cancel { 
          background: #6c757d; 
        }
        .instruction {
          color: #666;
          font-size: 0.9em;
          margin: 15px 0;
        }
      </style>
    </head>
    <body>
      <div class="gcash-container">
        <div class="gcash-logo">GCash</div>
        <h2>Payment Request</h2>
        <div class="amount">₱${amount.toLocaleString()}</div>
        
        <div class="details">
          <p><strong>Merchant:</strong> Heart Of D' Ocean Beach Resort</p>
          <p><strong>Booking For:</strong> ${bookingData.name}</p>
          <p><strong>Package:</strong> ${bookingData.room.split('—')[0].trim()}</p>
          <p><strong>Check-in:</strong> ${bookingData.date}</p>
          <p><strong>Guests:</strong> ${bookingData.guests}</p>
          <p><strong>Reference ID:</strong> ${bookingData.bookingId}</p>
        </div>
        
        <p class="instruction">Scan QR code below to pay</p>
        
        <div class="qr-container">
          <div class="qr-placeholder">📱</div>
          <div style="font-size: 0.8em; color: #666; margin-top: 10px;">
            GCash QR Code<br>
            <small>Point your GCash app to scan</small>
          </div>
        </div>
        
        <p class="instruction">Or enter mobile number: <strong>0917-123-4567</strong></p>
        
        <div style="margin-top: 25px;">
          <button class="btn success" onclick="paySuccess()">💳 Simulate Payment</button>
          <button class="btn cancel" onclick="window.close()">❌ Cancel</button>
        </div>
      </div>
      
      <script>
        function paySuccess() {
          const successData = {
            payment: 'success',
            booking: ${JSON.stringify(bookingData)},
            amount: ${amount},
            transactionId: 'GC' + Date.now()
          };
          
          alert('💰 Payment Successful!\\\\n\\\\nAmount: ₱${amount.toLocaleString()}\\\\nReference: ${bookingData.bookingId}\\\\n\\\\nThank you for your booking!');
          
          // Send success message back to main window
          if (window.opener && !window.opener.closed) {
            window.opener.postMessage(successData, '*');
          }
          
          window.close();
        }
      </script>
    </body>
    </html>
  `);
}

function calculateGCashAmount(room, guests) {
  const prices = {
    'Cottage A — ₱4,000': 4000,
    'Cottage B — ₱2,500': 2500,
    'Day Trip — ₱1,200': 1200 * parseInt(guests || 1)
  };
  return prices[room] || 0;
}

// Handle payment success from GCash window
window.addEventListener('message', function(event) {
  if (event.data && event.data.payment === 'success') {
    // Show success message on main page
    showMessage('🎉 Payment successful! Your booking is confirmed. We\'ve sent a confirmation email.', 'success');
    
    // Update booking status
    const bookings = JSON.parse(localStorage.getItem('bookings') || '[]');
    const lastBooking = bookings[bookings.length - 1];
    if (lastBooking) {
      lastBooking.paymentStatus = 'paid';
      lastBooking.transactionId = event.data.transactionId;
      localStorage.setItem('bookings', JSON.stringify(bookings));
    }
    
    // Show confirmation details
    setTimeout(() => {
      alert(`🏝️ Booking Confirmed!\\n\\nName: ${event.data.booking.name}\\nAmount: ₱${event.data.amount.toLocaleString()}\\nReference: ${event.data.booking.bookingId}\\n\\nThank you for choosing Heart Of D' Ocean!`);
    }, 1000);
  }
});

function showMessage(message, type = 'info') {
  const formMessage = document.getElementById('formMessage');
  if (formMessage) {
    formMessage.textContent = message;
    formMessage.className = type === 'success' ? 'success-message' : 'muted';
    formMessage.style.display = 'block';
    
    setTimeout(() => {
      formMessage.style.display = 'none';
    }, 5000);
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
  const modalTriggers = document.querySelectorAll('.image-modal-trigger');

  // Cottage data for the modal - COMPLETE VERSION
  const cottageData = {
    "WHITE HOUSE": {
      image: "https://images.unsplash.com/photo-1582268611958-ebfd161ef9cf?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "A luxurious beachfront cottage with panoramic ocean views and premium amenities.",
      capacity: "Up to 6 guests",
      price: "₱30,000",
      bestFor: "Families, groups",
      location: "Beachfront",
      amenities: ["Private balcony", "Ocean view", "King-size bed", "Air conditioning", "Kitchenette", "Free WiFi"]
    },
    "PENTHOUSE": {
      image: "https://images.unsplash.com/photo-1564078516393-cf04bd966897?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Our most exclusive accommodation with 360-degree views and premium luxury features.",
      capacity: "Up to 4 guests",
      price: "₱12,800",
      bestFor: "Couples, luxury travelers",
      location: "Top floor",
      amenities: ["Private rooftop terrace", "Jacuzzi", "Premium linens", "Smart TV", "Mini bar", "Concierge service"]
    },
    "AQUA CLASS": {
      image: "https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Modern cottage with direct pool access and contemporary design elements.",
      capacity: "Up to 4 guests",
      price: "₱11,800",
      bestFor: "Couples, small families",
      location: "Poolside",
      amenities: ["Direct pool access", "Contemporary design", "Queen-size bed", "Private patio", "Coffee maker", "Smart TV"]
    },
    "HEARTSUITE": {
      image: "https://images.unsplash.com/photo-1611892440504-42a792e24d32?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Romantic suite designed for couples with special touches and intimate atmosphere.",
      capacity: "2 guests",
      price: "₱11,800",
      bestFor: "Couples, honeymooners",
      location: "Garden view",
      amenities: ["Romantic decor", "King-size bed", "Private garden", "Champagne on arrival", "Bathrobes", "Special turndown service"]
    },
    "STEPH'S SKYLOUNGE 842/844": {
      image: "https://images.unsplash.com/photo-1590490360182-c33d57733427?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Spacious interconnected rooms perfect for families or groups traveling together.",
      capacity: "Up to 8 guests",
      price: "₱11,800",
      bestFor: "Families, groups",
      location: "Upper floor with ocean view",
      amenities: ["Interconnected rooms", "Ocean view balcony", "Two bathrooms", "Sofa bed", "Dining area", "Coffee station"]
    },
    "STEPH'S 846": {
      image: "https://images.unsplash.com/photo-1578683010236-d716f9a3f461?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Comfortable cottage with modern amenities and convenient access to resort facilities.",
      capacity: "Up to 3 guests",
      price: "₱10,000",
      bestFor: "Solo travelers, couples",
      location: "Central resort area",
      amenities: ["Queen-size bed", "Desk area", "Mini fridge", "Coffee maker", "Free WiFi", "Resort access"]
    },
    "STEPH'S 848": {
      image: "https://images.unsplash.com/photo-1566665797739-1674de7a421a?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Cozy cottage with garden views and comfortable furnishings for a relaxing stay.",
      capacity: "Up to 3 guests",
      price: "₱10,800",
      bestFor: "Solo travelers, couples",
      location: "Garden view",
      amenities: ["Queen-size bed", "Garden view", "Sitting area", "Mini fridge", "Coffee maker", "Free WiFi"]
    },
    "DE LUXE": {
      image: "https://images.unsplash.com/photo-1631049307264-da0ec9d70304?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Premium accommodation with upgraded amenities and stylish interior design.",
      capacity: "Up to 4 guests",
      price: "₱8,800",
      bestFor: "Couples, small families",
      location: "Beachfront",
      amenities: ["Ocean view", "King-size bed", "Sofa bed", "Premium toiletries", "Nespresso machine", "Smart TV"]
    },
    "BEATRICE A": {
      image: "https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Charming cottage with traditional design elements and modern comforts.",
      capacity: "Up to 4 guests",
      price: "₱7,800",
      bestFor: "Couples, small families",
      location: "Garden area",
      amenities: ["Traditional design", "Queen-size bed", "Private veranda", "Ceiling fan", "Mini fridge", "Coffee maker"]
    },
    "BEATRICE B": {
      image: "https://images.unsplash.com/photo-1564501049412-61c2a3083791?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Comfortable cottage with garden access and relaxing atmosphere.",
      capacity: "Up to 4 guests",
      price: "₱6,800",
      bestFor: "Couples, small families",
      location: "Garden area",
      amenities: ["Garden access", "Queen-size bed", "Private seating area", "Ceiling fan", "Mini fridge", "Coffee maker"]
    },
    "CONCIERGE 815/819": {
      image: "https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Interconnected rooms with premium concierge service and exclusive amenities.",
      capacity: "Up to 6 guests",
      price: "₱8,800",
      bestFor: "Families, business travelers",
      location: "Main building",
      amenities: ["Interconnected rooms", "Concierge service", "Work desk", "Premium linens", "Coffee station", "Free WiFi"]
    },
    "CONCIERGE 817": {
      image: "https://images.unsplash.com/photo-1591088398332-8a7791972843?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Premium room with dedicated concierge service and business-friendly amenities.",
      capacity: "Up to 3 guests",
      price: "₱9,800",
      bestFor: "Business travelers, couples",
      location: "Main building",
      amenities: ["Concierge service", "Work desk", "Premium linens", "Coffee maker", "Mini bar", "Free WiFi"]
    },
    "PREMIUM 838": {
      image: "https://images.unsplash.com/photo-1578662996442-48f60103fc96?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Upgraded accommodation with premium features and stylish design.",
      capacity: "Up to 4 guests",
      price: "₱7,800",
      bestFor: "Couples, small families",
      location: "Beach view",
      amenities: ["Beach view", "King-size bed", "Sofa bed", "Premium toiletries", "Coffee maker", "Smart TV"]
    },
    "PREMIUM 840": {
      image: "https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Spacious premium cottage with modern amenities and comfortable living space.",
      capacity: "Up to 4 guests",
      price: "₱8,800",
      bestFor: "Couples, small families",
      location: "Beach view",
      amenities: ["Beach view", "King-size bed", "Sitting area", "Premium toiletries", "Coffee maker", "Smart TV"]
    },
    "GIANT KUBO": {
      image: "https://images.unsplash.com/photo-1586375300773-8384e3e4916f?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Traditional Filipino-style cottage with modern amenities and spacious layout.",
      capacity: "Up to 8 guests",
      price: "₱6,800",
      bestFor: "Large families, groups",
      location: "Garden area",
      amenities: ["Traditional design", "Spacious layout", "Multiple beds", "Private bathroom", "Dining area", "Garden view"]
    },
    "SEASIDE (WHOLE)": {
      image: "https://images.unsplash.com/photo-1512918728675-ed5a9ecdebfd?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Complete seaside cottage with direct beach access and panoramic ocean views.",
      capacity: "Up to 6 guests",
      price: "₱6,800",
      bestFor: "Families, groups",
      location: "Beachfront",
      amenities: ["Direct beach access", "Ocean view", "Multiple bedrooms", "Full kitchen", "Living area", "Private terrace"]
    },
    "SEASIDE (HALF)": {
      image: "https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Cozy seaside accommodation with beach proximity and comfortable amenities.",
      capacity: "Up to 4 guests",
      price: "₱3,400",
      bestFor: "Couples, small families",
      location: "Beachfront",
      amenities: ["Beach proximity", "Ocean view", "Queen-size bed", "Kitchenette", "Private balcony", "Free WiFi"]
    },
    "BAMBOO KUBO": {
      image: "https://images.unsplash.com/photo-1552733407-5d5c46c3bb3b?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80",
      description: "Authentic bamboo cottage offering a traditional Filipino experience with modern comforts.",
      capacity: "Up to 4 guests",
      price: "₱2,800",
      bestFor: "Couples, cultural experience seekers",
      location: "Garden area",
      amenities: ["Traditional bamboo construction", "Queen-size bed", "Private bathroom", "Ceiling fan", "Mini fridge", "Garden view"]
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
}

// Page-specific initialization
function initPageSpecificFeatures() {
  const currentPage = window.location.pathname.split('/').pop();
  
  if (currentPage === 'booking.html') {
    initBookingPage();
  }
  
  if (currentPage === 'gallery.html') {
    initLightbox();
  }
  
  if (currentPage === 'rooms.html') {
    initRoomsPage();
  }
}

// Initialize everything when page loads
document.addEventListener('DOMContentLoaded', function() {
  setActivePage();
  initPageSpecificFeatures();
  
  console.log('🏝️ Heart Of D\' Ocean Beach Resort website loaded successfully!');
});

