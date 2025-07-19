
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root {
        --dark-blue: #1A2B40;
        --purple: #8A2BE2;
        --white: #FFFFFF;
        --light-gray: #F0F0F0;
        --text-color: #333333;
        --border-radius: 12px;
        --shadow: 0 2px 5px rgba(0,0,0,0.1);
        --transition: all 0.3s ease;
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: var(--light-gray);
        color: var(--text-color);
        line-height: 1.6;
        min-height: 100vh;
    }
    
    .container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    header {
        text-align: center;
        margin-bottom: 2rem;
    }
    
    header h1 {
        font-size: 1.8rem;
        font-weight: bold;
        color: var(--dark-blue);
    }
    
    header p {
        font-size: 1rem;
        max-width: 700px;
        margin: 0.5rem auto 0;
        color: var(--text-color);
        opacity: 0.8;
    }
    
    .services-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 1.6rem;
        margin-bottom: 2rem;
    }
    
    .service-btn {
        background: var(--white);
        border: 2px solid var(--dark-blue);
        border-radius: 10px;
        padding: 1rem;
        box-shadow: var(--shadow);
        transition: var(--transition);
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        text-decoration: none;
        color: var(--text-color);
        font-weight: 500;
        height: 120px;
    }
    
    .service-btn:hover {
        background: linear-gradient(45deg, var(--purple), #A020F0);
        color: var(--white);
        transform: translateY(-3px);
    }
    
    .service-btn:hover .service-icon {
        color: var(--white);
        transform: scale(1.1);
    }
    
    .service-icon {
        font-size: 1.8rem;
        margin-bottom: 0.5rem;
        color: var(--purple);
        transition: var(--transition);
    }
    
    .service-btn span {
        font-size: 0.9rem;
    }
    
    
    @media (max-width: 768px) {
        .container {
            padding: 0.8rem 1rem;
        }
        
        .services-grid {
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 0.6rem;
        }
        
        header h1 {
            font-size: 1.4rem;
        }
        
        header p {
            font-size: 0.9rem;
        }
        
        .service-btn {
            height: 100px;
            padding: 0.8rem;
        }
        
        .service-icon {
            font-size: 1.6rem;
            margin-bottom: 0.4rem;
        }
        
        .service-btn span {
            font-size: 0.8rem;
        }
    }
    
    @media (max-width: 480px) {
        .services-grid {
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.4rem;
        }
        
        header h1 {
            font-size: 1.2rem;
        }
        
        header p {
            font-size: 0.85rem;
        }
        
        .service-btn {
            height: 90px;
            padding: 0.6rem;
        }
        
        .service-icon {
            font-size: 1.4rem;
        }
        
        .service-btn span {
            font-size: 0.75rem;
        }
    }
</style>
</head>
<body>
    <div class="container">
        <header>
            <h1 data-translate="header-title">Health Services</h1>
            <p data-translate="header-desc">Access comprehensive healthcare services with just one click</p>
        </header>
        
        <div class="services-grid">
            <a href="doctor.php" class="service-btn">
                <i class="fas fa-user-md service-icon"></i>
                <span data-translate="service-doctors">Doctors</span>
            </a>
            <a href="#" class="service-btn">
                <i class="fas fa-stethoscope service-icon"></i>
                <span data-translate="service-disease-check">Disease Check</span>
            </a>
            <a href="#" class="service-btn">
                <i class="fas fa-calendar-check service-icon"></i>
                <span data-translate="service-appointments">Appointments</span>
            </a>
            <a href="fund_raise.php" class="service-btn">
                <i class="fas fa-donate service-icon service-icon"></i>
                <span data-translate="service-Fund Raise">Fund Raise</span>
            </a>
            <a href="#" class="service-btn">
                <i class="fas fa-heartbeat service-icon"></i>
                <span data-translate="service-health-tips">Health Tips</span>
            </a>
            <a href="#" class="service-btn">
                <i class="fas fa-vial service-icon"></i>
                <span data-translate="service-lab-tests">Lab Tests</span>
            </a>
            <a href="emergency.php" class="service-btn">
                <i class="fas fa-ambulance service-icon"></i>
                <span data-translate="service-emergency">Emergency</span>
            </a>
            <a href="#" class="service-btn">
                <i class="fas fa-file-medical service-icon"></i>
                <span data-translate="service-medical-records">Medical Records</span>
            </a>
            <a href="#" class="service-btn">
                <i class="fas fa-brain service-icon"></i>
                <span data-translate="service-mental-health">Mental Health</span>
            </a>
            <a href="#" class="service-btn">
                <i class="fas fa-chart-line service-icon"></i>
                <span data-translate="service-wellness-check">Wellness Check</span>
            </a>
            <a href="#" class="service-btn">
                <i class="fas fa-chart-line service-icon"></i>
                <span data-translate="service-aboutus">Wellness Check</span>
            </a>

                        <a href="contact.php" class="service-btn">
                <i class="fas fa-phone-alt service-icon"></i>
                <span data-translate="service-aboutus">Contact Us</span>
            </a>

            <a href="notice.php" class="service-btn">
                <i class="fas fa-notes-medical service-icon"></i>
                <span data-translate="service-aboutus">Notice</span>
            </a>
            <a href="medicine.php" class="service-btn">
                <i class="fas fa-pills service-icon"></i>
                <span data-translate="service-aboutus">Medicine</span>
            </a>

        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const languageSelect = document.getElementById("language-select");
            const updateLanguage = (lang) => {
                const mapping = {
                    en: {
                        'header-title': 'Health Services',
                        'header-desc': 'Access comprehensive healthcare services with just one click',
                        'service-doctors': 'Doctors',
                        'service-disease-check': 'Disease Check',
                        'service-appointments': 'Appointments',
                        'service-prescriptions': 'Prescriptions',
                        'service-health-tips': 'Health Tips',
                        'service-lab-tests': 'Lab Tests',
                        'service-emergency': 'Emergency',
                        'service-medical-records': 'Medical Records',
                        'service-mental-health': 'Mental Health',
                        'service-wellness-check': 'Wellness Check'
                    },
                    np: {
                        'header-title': 'स्वास्थ्य सेवाहरू',
                        'header-desc': 'एउटा क्लिकमा व्यापक स्वास्थ्य सेवाहरू पहुँच गर्नुहोस्',
                        'service-doctors': 'डाक्टरहरू',
                        'service-disease-check': 'रोग जाँच',
                        'service-appointments': 'भेटघाट',
                        'service-prescriptions': 'प्रेस्क्रिप्शनहरू',
                        'service-health-tips': 'स्वास्थ्य सुझावहरू',
                        'service-lab-tests': 'प्रयोगशाला परीक्षणहरू',
                        'service-emergency': 'आपतकालीन',
                        'service-medical-records': 'मेडिकल रेकर्डहरू',
                        'service-mental-health': 'मानसिक स्वास्थ्य',
                        'service-wellness-check': 'स्वास्थ्य जाँच'
                    }
                };

                // Update services page content
                document.querySelectorAll('[data-translate]').forEach(el => {
                    const key = el.getAttribute('data-translate');
                    if (mapping[lang] && mapping[lang][key]) {
                        el.textContent = mapping[lang][key];
                    }
                });
            };

           
            const savedLang = localStorage.getItem('language') || 'en';
            updateLanguage(savedLang);

           
            if (languageSelect) {
                languageSelect.addEventListener("change", function () {
                    updateLanguage(this.value);
                });
            }
        });
    </script>
</body>
</html>