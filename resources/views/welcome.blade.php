<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <!-- Basic Meta -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('landing.page_title') }}</title>

    <meta name="description"
        content="ATTP (African Think Tank Platform Administration) is a pan-African knowledge and policy coordination platform supporting African Union institutions, think tanks, and development partners." />

    <meta name="keywords"
        content="ATTP, African Think Tank Platform, African Union policy, Africa governance, policy research Africa, AU think tanks, development policy Africa, public policy Africa, African institutions, research platform Africa" />

    <meta name="author" content="African Think Tank Platform Administration (ATTP)" />
    <meta name="robots" content="index, follow" />
    <meta name="language" content="en" />
    <meta name="theme-color" content="#0B4F6C" />

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('assets/images/au3.jpg') }}" type="image/png" />

    <!-- Open Graph -->
    <meta property="og:type" content="website" />
    <meta property="og:title" content="ATTP ? African Think Tank Platform Administration" />
    <meta property="og:description"
        content="Strengthening Africa?s policy ecosystem through collaboration, research, and institutional coordination in support of the African Union." />
    <meta property="og:image" content="https://africathinktankplatform.africa/assets/images/au3.jpg" />
    <meta property="og:url" content="https://africathinktankplatform.africa/" />
    <meta property="og:site_name" content="ATTP" />

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="ATTP ? African Think Tank Platform Administration" />
    <meta name="twitter:description"
        content="A pan-African platform advancing policy research, governance, and institutional collaboration across Africa." />
    <meta name="twitter:image" content="https://africathinktankplatform.africa/assets/images/au.png" />
    <meta name="twitter:site" content="@ATTP_Africa" />

    <!-- Canonical URL -->
    <link rel="canonical" href="https://africathinktankplatform.africa/" />

    <!-- Fonts & Styles -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}" />

    <!-- RTL CSS for Arabic -->
    @if (app()->getLocale() === 'ar')
        <link rel="stylesheet" href="{{ asset('assets/css/rtl.css') }}">
    @endif

    <!-- Schema.org Markup -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "African Think Tank Platform Administration",
      "alternateName": "ATTP",
      "url": "https://africathinktankplatform.africa",
      "logo": "https://africathinktankplatform.africa/assets/images/au3.jpg",
      "description": "ATTP is a pan-African policy and research coordination platform supporting African Union institutions, think tanks, and development partners to strengthen governance and evidence-based decision-making across Africa.",
      "foundingLocation": {
        "@type": "Place",
        "name": "Africa"
      },
      "sameAs": [
        "https://www.linkedin.com/company/attp-africa",
        "https://twitter.com/ATTP_Africa"
      ]
    }
    </script>
</head>

<style>
    .annoucment {
        display: flex;
        flex-wrap: wrap;
        gap: 24px;
    }

    .annoucment .flow-card {
        width: calc(33.333% - 16px);
    }

    /* Tablet */
    @media (max-width: 992px) {
        .annoucment .flow-card {
            width: calc(50% - 12px);
        }
    }

    /* Mobile */
    @media (max-width: 576px) {
        .annoucment .flow-card {
            width: 100%;
        }
    }
</style>



<body>
    <!-- ====== NAVBAR ====== -->
    <header class="navbar">
        <div class="logo">
            {{-- ATTP<span>.africa</span> --}}
            <img src="{{ asset('assets/images/au.png ') }}" alt="ATTP" class="logo logo-sm">

        </div>
        <nav class="nav-links">
            <a href="{{ route('landing.index') }}">{{ __('navigation.home') }}</a>
            <a href="#annoucements">{{ __('landing.announcements') }}</a>
            <a href="{{ route('events') }}">{{ __('landing.events_webinars') }}</a>
            <a href="{{ route('impact.map') }}">{{ __('navigation.impact_map') }}</a>
            {{-- <a href="{{ route('landing.african_map') }}">AFRICAN MAP</a> --}}
            <a href="#contact">{{ __('navigation.contact') }}</a>
            <a href="{{ route('careers.index') }}">{{ __('navigation.careers') }}</a>
        </nav>

        <div class="nav-actions">
            <x-language-selector style="landing" />
            <a href="{{ route('login') }}" class="btn btn-login">{{ __('navigation.login') }}</a>
            <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">
                {{ __('landing.policy_programs') }}
            </a>


        </div>
    </header>

    <!-- ====== HERO SECTION ====== -->
    <section class="hero">
        <div class="overlay"></div>

        <div class="slider">
            <div class="slide active" style="background-image: url('{{ asset('assets/images/au1.jpg') }}');"></div>
            <div class="slide" style="background-image: url('{{ asset('assets/images/au2.webp') }}');"></div>
            <div class="slide" style="background-image: url('{{ asset('assets/images/au3.jpg') }}');"></div>
        </div>

        <div class="hero-content">
            <br>
            <br>
            <h1 id="typewriter" class="typing-text"></h1>
            <p>
                {{ __('landing.hero_subtitle') }}
            </p>

            <a href="{{ route('public.procurement.index') }}" class="cta-btn">
                {{ __('landing.hero_cta') }}
            </a>
        </div>
    </section>

    <!-- ====== SYSTEM PROCESS FLOW ====== -->
    <!-- ====== SYSTEM PROCESS FLOW ====== -->
    <!-- ====== SYSTEM PROCESS FLOW ====== -->
    <section id="process" class="process-section">
        <h2>{{ __('landing.process_title') }}</h2>
        <p>
            {{ __('landing.process_subtitle') }}
        </p>

        <div class="process-flow">

            <div class="flow-card">
                <span>1</span>
                <h3>{{ __('landing.step1_title') }}</h3>
                <p>
                    {{ __('landing.step1_desc') }}
                </p>
            </div>

            <div class="flow-card">
                <span>2</span>
                <h3>{{ __('landing.step2_title') }}</h3>
                <p>
                    {{ __('landing.step2_desc') }}
                </p>
            </div>

            <div class="flow-card">
                <span>3</span>
                <h3>{{ __('landing.step3_title') }}</h3>
                <p>
                    {{ __('landing.step3_desc') }}
                </p>
            </div>

            <div class="flow-card">
                <span>4</span>
                <h3>{{ __('landing.step4_title') }}</h3>
                <p>
                    {{ __('landing.step4_desc') }}
                </p>
            </div>

            <div class="flow-card">
                <span>5</span>
                <h3>{{ __('landing.step5_title') }}</h3>
                <p>
                    {{ __('landing.step5_desc') }}
                </p>
            </div>

            <div class="flow-card">
                <span>6</span>
                <h3>{{ __('landing.step6_title') }}</h3>
                <p>
                    {{ __('landing.step6_desc') }}
                </p>
            </div>

            <div class="flow-card">
                <span>7</span>
                <h3>{{ __('landing.step7_title') }}</h3>
                <p>
                    {{ __('landing.step7_desc') }}
                </p>
            </div>

            <div class="flow-card">
                <span>8</span>
                <h3>{{ __('landing.step8_title') }}</h3>
                <p>
                    {{ __('landing.step8_desc') }}
                </p>
            </div>

        </div>
    </section>
    <section id="annoucements" class="process-section">
        <h2>{{ __('landing.grants_title') }}</h2>
        <p>
            {{ __('landing.grants_subtitle') }}
        </p>

        <div class="annoucment">

            <div class="flow-card">
                <span>1</span>
                <h3>English (EN)</h3>
                <p>
                    {{ __('landing.download_en') }}
                </p>
                <a href="{{ asset('assets/award/ATTP_Award_Announcement_EN.pdf') }}" class="btn-view" target="_blank"
                    rel="noopener noreferrer">
                    {{ __('landing.download_version', ['language' => 'English']) }}
                </a>
            </div>

            <div class="flow-card">
                <span>2</span>
                <h3>French (FR)</h3>
                <p>
                    {{ __('landing.download_fr') }}
                </p>
                <a href="{{ asset('assets/award/ATTP_Award_Announcement_FR.pdf') }}" class="btn-view"
                    target="_blank" rel="noopener noreferrer">
                    {{ __('landing.download_version', ['language' => 'French']) }}
                </a>
            </div>

            <div class="flow-card">
                <span>3</span>
                <h3>Arabic (AR)</h3>
                <p>
                    {{ __('landing.download_ar') }}
                </p>
                <a href="{{ asset('assets/award/ATTP_Award_Announcement_AR.pdf') }}" class="btn-view"
                    target="_blank" rel="noopener noreferrer">
                    {{ __('landing.download_version', ['language' => 'Arabic']) }}
                </a>
            </div>

            <div class="flow-card">
                <span>4</span>
                <h3>Portuguese (PT)</h3>
                <p>
                    {{ __('landing.download_pt') }}
                </p>
                <a href="{{ asset('assets/award/ATTP_Award_Announcement_PT.pdf') }}" class="btn-view"
                    target="_blank" rel="noopener noreferrer">
                    {{ __('landing.download_version', ['language' => 'Portuguese']) }}
                </a>
            </div>

            <div class="flow-card">
                <span>5</span>
                <h3>Spanish (ES)</h3>
                <p>
                    {{ __('landing.download_es') }}
                </p>
                <a href="{{ asset('assets/award/ATTP_Award_Announcement_ES.pdf') }}" class="btn-view"
                    target="_blank" rel="noopener noreferrer">
                    {{ __('landing.download_version', ['language' => 'Spanish']) }}
                </a>
            </div>

            <div class="flow-card">
                <span>6</span>
                <h3>Swahili (SW)</h3>
                <p>
                    {{ __('landing.download_sw') }}
                </p>
                <a href="{{ asset('assets/award/ATTP_Award_Announcement_SW.pdf') }}" class="btn-view"
                    target="_blank" rel="noopener noreferrer">
                    {{ __('landing.download_version', ['language' => 'Swahili']) }}
                </a>
            </div>

        </div>


    </section>





    <!-- ====== CUSTOMIZATION SECTION ====== -->
    <section id="customization" class="customization-section">
        <div class="content">
            <h2>{{ __('landing.governance_title') }}</h2>
            <p>
                {{ __('landing.governance_subtitle') }}
            </p>

            <ul>
                <li>{{ __('landing.governance_item1') }}</li>
                <li>{{ __('landing.governance_item2') }}</li>
                <li>{{ __('landing.governance_item3') }}</li>
                <li>{{ __('landing.governance_item4') }}</li>
                <li>{{ __('landing.governance_item5') }}</li>
            </ul>
        </div>
    </section>



    <!-- ====== FOOTER ====== -->
    <footer id="contact" class="footer">
        <div class="footer-content">

            <div class="footer-logo">
                <h3>ATTP<span> Administration</span></h3>
                <p>
                    {{ __('landing.footer_description') }}
                </p>
            </div>

            <div class="footer-links">
                <h4>{{ __('landing.footer_links_title') }}</h4>
                <a href="#">{{ __('landing.footer_link_home') }}</a>
                <a href="#process">{{ __('landing.footer_link_process') }}</a>
                <a href="#customization">{{ __('landing.footer_link_oversight') }}</a>
                <a href="#contact">{{ __('navigation.contact') }}</a>
            </div>

            <div class="footer-contact">
                <h4>{{ __('landing.footer_contact_title') }}</h4>
                <p>{{ __('landing.footer_email') }}</p>
                <p>{{ __('landing.footer_copyright', ['year' => date('Y')]) }}</p>
            </div>

        </div>

        <p style="margin-top: 10px; font-weight: 600; text-align: center;">
            Supporting African Union policy coordination, governance reform,
            and evidence-based decision-making across the continent.
        </p>

    </footer>



    <script src="{{ asset('assets/script.js') }}"></script>
    <!--Start of Tawk.to Script-->
    <!--Start of Tawk.to Script-->
    <script type="text/javascript">
        var Tawk_API = Tawk_API || {},
            Tawk_LoadStart = new Date();
        (function() {
            var s1 = document.createElement("script"),
                s0 = document.getElementsByTagName("script")[0];
            s1.async = true;
            s1.src = 'https://embed.tawk.to/6968b44f895de4198b902486/1jf0g0m8k';
            s1.charset = 'UTF-8';
            s1.setAttribute('crossorigin', '*');
            s0.parentNode.insertBefore(s1, s0);
        })();
    </script>
    <!--End of Tawk.to Script-->
    <!--End of Tawk.to Script-->
</body>

</html>
