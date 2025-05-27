// Simple translation system - text only, no style changes
// Using direct translation dictionaries for reliability

// Current language
let currentLanguage = 'en';

// Available languages with their codes
const availableLanguages = {
    'en': 'English',
    'es': 'Spanish',
    'fr': 'French',
    'de': 'German',
    'it': 'Italian'
};

// Translation dictionaries
const translations = {
    en: {
        home: "Home",
        search: "Search",
        about: "About",
        contact: "Contact",
        login: "Login",
        signup: "Sign Up",
        myAccount: "My Account",
        profile: "Profile",
        reservations: "My Reservations",
        reviews: "My Reviews",
        logout: "Logout",
        getInTouch: "Get in Touch",
        fullName: "Full Name",
        email: "Email Address",
        subject: "Subject",
        message: "Message",
        send: "Send Message",
        address: "Address",
        phone: "Phone",
        follow: "Follow Us",
        language: "Language",
        currency: "Currency",
        restaurantReview: "Restaurant Review",
        quickLinks: "Quick Links",
        aboutUs: "About Us",
        termsOfService: "Terms of Service",
        privacyPolicy: "Privacy Policy",
        newsletter: "Newsletter",
        subscribe: "Subscribe",
        popularCategories: "Popular Categories",
        italianRestaurants: "Italian Restaurants",
        japaneseRestaurants: "Japanese Restaurants",
        mexicanRestaurants: "Mexican Restaurants",
        indianRestaurants: "Indian Restaurants",
        officeHours: "Office Hours"
    },
    es: {
        home: "Inicio",
        search: "Buscar",
        about: "Acerca de",
        contact: "Contacto",
        login: "Iniciar sesión",
        signup: "Registrarse",
        myAccount: "Mi cuenta",
        profile: "Perfil",
        reservations: "Mis reservas",
        reviews: "Mis reseñas",
        logout: "Cerrar sesión",
        getInTouch: "Contactar",
        fullName: "Nombre completo",
        email: "Correo electrónico",
        subject: "Asunto",
        message: "Mensaje",
        send: "Enviar mensaje",
        address: "Dirección",
        phone: "Teléfono",
        follow: "Síguenos",
        language: "Idioma",
        currency: "Moneda",
        restaurantReview: "Reseñas de Restaurantes",
        quickLinks: "Enlaces rápidos",
        aboutUs: "Sobre nosotros",
        termsOfService: "Términos de servicio",
        privacyPolicy: "Política de privacidad",
        newsletter: "Boletín",
        subscribe: "Suscribirse",
        popularCategories: "Categorías populares",
        italianRestaurants: "Restaurantes italianos",
        japaneseRestaurants: "Restaurantes japoneses",
        mexicanRestaurants: "Restaurantes mexicanos",
        indianRestaurants: "Restaurantes indios",
        officeHours: "Horario de oficina"
    },
    fr: {
        home: "Accueil",
        search: "Recherche",
        about: "À propos",
        contact: "Contact",
        login: "Connexion",
        signup: "S'inscrire",
        myAccount: "Mon compte",
        profile: "Profil",
        reservations: "Mes réservations",
        reviews: "Mes avis",
        logout: "Déconnexion",
        getInTouch: "Contactez-nous",
        fullName: "Nom complet",
        email: "Adresse e-mail",
        subject: "Sujet",
        message: "Message",
        send: "Envoyer le message",
        address: "Adresse",
        phone: "Téléphone",
        follow: "Suivez-nous",
        language: "Langue",
        currency: "Devise",
        restaurantReview: "Critique de Restaurant",
        quickLinks: "Liens rapides",
        aboutUs: "À propos de nous",
        termsOfService: "Conditions d'utilisation",
        privacyPolicy: "Politique de confidentialité",
        newsletter: "Bulletin d'information",
        subscribe: "S'abonner",
        popularCategories: "Catégories populaires",
        italianRestaurants: "Restaurants italiens",
        japaneseRestaurants: "Restaurants japonais",
        mexicanRestaurants: "Restaurants mexicains",
        indianRestaurants: "Restaurants indiens",
        officeHours: "Heures d'ouverture"
    },
    de: {
        home: "Startseite",
        search: "Suche",
        about: "Über uns",
        contact: "Kontakt",
        login: "Anmelden",
        signup: "Registrieren",
        myAccount: "Mein Konto",
        profile: "Profil",
        reservations: "Meine Reservierungen",
        reviews: "Meine Bewertungen",
        logout: "Abmelden",
        getInTouch: "Kontaktieren Sie uns",
        fullName: "Vollständiger Name",
        email: "E-Mail-Adresse",
        subject: "Betreff",
        message: "Nachricht",
        send: "Nachricht senden",
        address: "Adresse",
        phone: "Telefon",
        follow: "Folgen Sie uns",
        language: "Sprache",
        currency: "Währung",
        restaurantReview: "Restaurant-Bewertung",
        quickLinks: "Schnelllinks",
        aboutUs: "Über uns",
        termsOfService: "Nutzungsbedingungen",
        privacyPolicy: "Datenschutzrichtlinie",
        newsletter: "Newsletter",
        subscribe: "Abonnieren",
        popularCategories: "Beliebte Kategorien",
        italianRestaurants: "Italienische Restaurants",
        japaneseRestaurants: "Japanische Restaurants",
        mexicanRestaurants: "Mexikanische Restaurants",
        indianRestaurants: "Indische Restaurants",
        officeHours: "Öffnungszeiten"
    },
    it: {
        home: "Home",
        search: "Cerca",
        about: "Chi siamo",
        contact: "Contatti",
        login: "Accedi",
        signup: "Registrati",
        myAccount: "Il mio account",
        profile: "Profilo",
        reservations: "Le mie prenotazioni",
        reviews: "Le mie recensioni",
        logout: "Esci",
        getInTouch: "Contattaci",
        fullName: "Nome completo",
        email: "Indirizzo email",
        subject: "Oggetto",
        message: "Messaggio",
        send: "Invia messaggio",
        address: "Indirizzo",
        phone: "Telefono",
        follow: "Seguici",
        language: "Lingua",
        currency: "Valuta",
        restaurantReview: "Recensioni Ristoranti",
        quickLinks: "Collegamenti rapidi",
        aboutUs: "Chi siamo",
        termsOfService: "Termini di servizio",
        privacyPolicy: "Politica sulla privacy",
        newsletter: "Newsletter",
        subscribe: "Iscriviti",
        popularCategories: "Categorie popolari",
        italianRestaurants: "Ristoranti italiani",
        japaneseRestaurants: "Ristoranti giapponesi",
        mexicanRestaurants: "Ristoranti messicani",
        indianRestaurants: "Ristoranti indiani",
        officeHours: "Orari d'ufficio"
    }
};

// Initialize translation
function initTranslation() {
    // Setup language selector
    const languageSelector = document.querySelector('.language-selector select');
    if (languageSelector) {
        // Set up languages
        setupLanguageDropdown(languageSelector);
        
        // Handle language change
        languageSelector.addEventListener('change', function() {
            const langCode = this.value;
            changeLanguage(langCode);
        });
        
        // Load saved language preference
        const savedLanguage = localStorage.getItem('selectedLanguage');
        if (savedLanguage && translations[savedLanguage]) {
            languageSelector.value = savedLanguage;
            changeLanguage(savedLanguage);
        }
    }
    
    // Add data-i18n attributes to elements for translation
    addI18nAttributes();
}

// Change the language
function changeLanguage(langCode) {
    // Check if translations exist for this language
    if (!translations[langCode]) {
        console.warn(`No translations available for ${langCode}`);
        return;
    }
    
    // Save the selected language
    localStorage.setItem('selectedLanguage', langCode);
    currentLanguage = langCode;
    
    // Apply translations to all elements with data-i18n attribute
    translatePage(langCode);
}

// Add data-i18n attributes to elements
function addI18nAttributes() {
    // Special handling for specific elements that are known to need translation
    const knownElements = {
        '.navbar-brand span': 'restaurantReview',
        '.nav-link[href="index.php"]': 'home',
        '.nav-link[href="search.php"]': 'search',
        '.nav-link[href="about.php"]': 'about',
        '.nav-link[href="contact.php"]': 'contact',
        '.dropdown-item[href="profile.php"]': 'profile',
        '.dropdown-item[href="reservations.php"]': 'reservations',
        '.dropdown-item[href="reviews.php"]': 'reviews',
        '.dropdown-item[href="logout.php"]': 'logout'
    };
    
    // Apply known elements first
    for (const selector in knownElements) {
        const element = document.querySelector(selector);
        if (element) {
            element.setAttribute('data-i18n', knownElements[selector]);
        }
    }
    
    // Match other elements with translations
    const elements = document.querySelectorAll('a, button, h1, h2, h3, h4, h5, h6, p, span, li, label');
    elements.forEach(element => {
        // Skip if already has data-i18n attribute
        if (element.hasAttribute('data-i18n')) return;
        
        const text = element.textContent.trim();
        if (!text) return;
        
        // Find matching translation key
        for (const key in translations.en) {
            if (translations.en[key] === text) {
                element.setAttribute('data-i18n', key);
                break;
            }
        }
    });
}

// Translate the page
function translatePage(langCode) {
    // Find all elements with data-i18n attribute
    const elements = document.querySelectorAll('[data-i18n]');
    
    elements.forEach(element => {
        const key = element.getAttribute('data-i18n');
        if (translations[langCode] && translations[langCode][key]) {
            // If it's an input or textarea, change the placeholder or value
            if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                if (element.getAttribute('placeholder')) {
                    element.setAttribute('placeholder', translations[langCode][key]);
                } else {
                    element.value = translations[langCode][key];
                }
            } else {
                // For other elements, change the text content only
                element.textContent = translations[langCode][key];
            }
        }
    });
    
    // Dispatch an event that other scripts can listen for
    document.dispatchEvent(new CustomEvent('languageChanged', { 
        detail: { language: langCode } 
    }));
}

// Setup language dropdown
function setupLanguageDropdown(dropdown) {
    // Clear and rebuild the dropdown
    dropdown.innerHTML = '';
    Object.keys(translations).forEach(code => {
        const option = document.createElement('option');
        option.value = code;
        option.textContent = availableLanguages[code];
        dropdown.appendChild(option);
    });
}

// Initialize when the page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize translation system
    initTranslation();
});