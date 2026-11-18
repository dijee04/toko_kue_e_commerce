<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dear Seana - Keajaiban Rasa Dalam Setiap Sematan</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Titan+One&family=Dancing+Script:wght@700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    <link rel="stylesheet" href="{{ asset('assets/user/css/style.css') }}">
    <meta name="description" content="Dear Seana menyediakan berbagai macam kue coklat, puding, dan roti premium.">
    <!-- Midtrans Snap JS -->
    @if(config('services.midtrans.is_production'))
        <script type="text/javascript" src="https://app.midtrans.com/snap/snap.js" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
    @else
        <script type="text/javascript" src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
    @endif
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
    
    <!-- Custom Style Lonceng Notifikasi Status Pesanan -->
    <style>
        .notification-dropdown-wrapper {
            position: relative;
        }
        #notificationBellBtn {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        #notificationBellBtn:hover {
            transform: scale(1.08) rotate(5deg);
            background: #ffebee !important;
            color: #d32f2f !important;
            box-shadow: 0 6px 15px rgba(229, 115, 115, 0.3) !important;
        }
        #notificationBellBtn:active {
            transform: scale(0.92);
        }
        .notification-item-link {
            display: flex; 
            gap: 14px; 
            padding: 14px 16px; 
            border-bottom: 1px solid #fce4ec; 
            text-decoration: none; 
            color: inherit; 
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); 
            margin: 8px 12px; 
            border-radius: 16px; 
            border: 1px solid rgba(229,115,115,0.05); 
            box-shadow: 0 4px 10px rgba(0,0,0,0.01);
        }
        .notification-item-link:hover {
            transform: translateY(-2px) scale(1.01);
            box-shadow: 0 6px 18px rgba(229, 115, 115, 0.1) !important;
            filter: brightness(0.98);
        }
        /* Custom Scrollbar */
        .notification-scrollable::-webkit-scrollbar {
            width: 6px;
        }
        .notification-scrollable::-webkit-scrollbar-track {
            background: transparent;
        }
        .notification-scrollable::-webkit-scrollbar-thumb {
            background: #f8bbd0;
            border-radius: 10px;
        }
        .notification-scrollable::-webkit-scrollbar-thumb:hover {
            background: #f06292;
            border-radius: 10px;
        }
        .notification-scrollable::-webkit-scrollbar-thumb:hover {
            background: #f06292;
        }

        /* Custom Premium Toast Pop-up */
        .toast-container {
            position: fixed;
            top: 25px;
            right: 25px;
            z-index: 999999;
            display: flex;
            flex-direction: column;
            gap: 15px;
            pointer-events: none;
        }
        .toast-card {
            pointer-events: auto;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            padding: 20px 24px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            gap: 18px;
            width: 385px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            transform: translateX(130%);
            opacity: 0;
            transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        .toast-card.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        /* Progress Bar Animation */
        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 4px;
            width: 100%;
            background: rgba(0, 0, 0, 0.05);
        }
        .toast-progress-bar {
            height: 100%;
            width: 100%;
            transition: width 8s linear;
        }

        @keyframes bounceNotif {
            0% { transform: translateY(0); }
            100% { transform: translateY(-3px); }
        }
    </style>

    {{-- Kunci penyimpanan keranjang dipisah per akun (guest / user id).
         Mencegah keranjang satu akun terbaca oleh akun lain di browser yang sama. --}}
    <script>
        window.CART_USER_SCOPE = '{{ auth()->check() ? 'u' . auth()->id() : 'guest' }}';
        window.CART_KEY = 'sweetSavoryCart_' + window.CART_USER_SCOPE;
        window.GALLERY_CART_KEY = window.CART_KEY + '_gallery';
        window.IS_AUTH = {{ auth()->check() ? 'true' : 'false' }};
        window.LOGIN_URL = @json(route('login'));
    </script>
    @include('partials.favicon')
</head>

<body>
    <!-- Topbar -->
    <div class="topbar container">
        <button type="button" class="nav-expand" id="navExpandBtn" aria-label="Tampilkan menu lengkap" title="Tampilkan menu lengkap">
            <i class="fas fa-chevron-right"></i>
        </button>
        <div class="logo-container">
            <div class="logo-sweet">SWEET & SAVORY</div>
            <a href="/" class="logo-group">
                <span class="logo-dear">
                    <span style="color: #D5BCCC;">D</span>
                    <span style="color: #C1D0AA;">e</span>
                    <span style="color: #EBE5B5;">a</span>
                    <span style="color: #E8BC85;">r</span>
                </span>
                <span class="logo-seana">Seana</span>
            </a>
        </div>
        <div class="topbar-right" style="gap: 2rem;">
            <ul class="nav-links">
                <li><a href="{{ route('beranda') }}" title="Home"><i class="fas fa-home"></i><span class="nav-label">Home</span></a></li>
                <li><a href="{{ route('menu') }}" title="Menu"><i class="fas fa-utensils"></i><span class="nav-label">Menu</span></a></li>
                <li><a href="{{ route('our_story') }}" title="Our Story"><i class="fas fa-heart"></i><span class="nav-label">Our Story</span></a></li>
                @auth
                    <li><a href="{{ route('pesanan_saya') }}" title="Pesanan Saya" style="color: #f06292; font-weight: 700;"><i class="fas fa-shopping-bag"></i><span class="nav-label">Pesanan Saya</span></a></li>
                    <li class="nav-logout" style="margin-left: 10px; padding-left: 10px; border-left: 1px solid #eee;">
                        <a href="#" title="Keluar" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" style="color: #c45b74 !important; font-weight: 700;">
                            <i class="fas fa-sign-out-alt"></i><span class="nav-label">Keluar</span>
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                            @csrf
                        </form>
                    </li>
                @endauth
            </ul>
            <div style="display: flex; align-items: center; gap: 15px;">
                @auth
                    <!-- Tombol Profil -->
                    <a href="{{ route('profil.index') }}" title="Profil Saya" style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: #fce4ec; color: #f06292; font-size: 1.2rem; transition: all 0.3s; text-decoration: none; box-shadow: 0 4px 10px rgba(240, 98, 146, 0.2);">
                        <i class="fas fa-user"></i>
                    </a>

                    <!-- Lonceng Notifikasi Status Pesanan -->
                    @php
                        $active_notification_count = \App\Models\Order::where('user_id', auth()->id())
                            ->whereIn('status', ['baru', 'disiapkan', 'dikirim'])
                            ->count();
                    @endphp
                    
                    <a href="{{ route('notifikasi') }}" id="notificationBellBtn" title="Notifikasi Status Pesanan" style="position: relative; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: #fff5f5; color: #e57373; font-size: 1.2rem; border: none; text-decoration: none; box-shadow: 0 4px 10px rgba(229, 115, 115, 0.15);">
                        <i class="fas fa-bell"></i>
                        @if($active_notification_count > 0)
                            <span class="notification-badge" style="position: absolute; top: -5px; right: -5px; display: flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 50%; background: #e57373; color: white; font-size: 10px; font-weight: 800; border: 2px solid white;">
                                {{ $active_notification_count }}
                            </span>
                        @endif
                    </a>
                @endauth

                @guest
                    <a href="{{ route('login') }}" title="Masuk" style="display:flex; align-items:center; gap:8px; padding: 10px 16px; border-radius: 12px; background: #fce4ec; color: #f06292; font-weight: 700; font-size: 0.9rem; text-decoration: none; border: 1px solid #f8bbd0; transition: all 0.2s;">
                        <i class="fas fa-sign-in-alt"></i><span class="nav-label">Masuk</span>
                    </a>
                @endguest

                <!-- Tombol Pesan Sekarang -->
                <a href="https://wa.me/{{ $global_setting->wa_number ?? '6281234567890' }}?text=Halo%20Dear%20Seana,%20saya%20tertarik%20dengan%20koleksi%20kue%20Anda."
                    target="_blank" class="btn-pesan" title="Pesan Sekarang" style="display:flex; align-items:center; gap:8px;"><i
                        class="fab fa-whatsapp" style="font-size:1.1rem;"></i><span class="nav-label">Pesan Sekarang</span></a>
            </div>
        </div>
    </div>

    <main>
        @yield('content')
    </main>

    <footer class="footer-crumbl">
        <div class="footer-socials">
            <a href="#"><i class="fab fa-facebook-f"></i></a>
            <a href="#"><i class="fab fa-instagram"></i></a>
            <a href="#"><i class="fab fa-tiktok"></i></a>
            <a href="#"><i class="fab fa-x-twitter"></i></a>
            <a href="#"><i class="fab fa-youtube"></i></a>
            <a href="#"><i class="fab fa-linkedin-in"></i></a>
            <a href="#"><i class="fab fa-pinterest-p"></i></a>
        </div>
        <div class="footer-bottom">
            <h1 class="giant-logo">Dear Seana</h1>
            <div class="footer-legal">
                <p>&copy; 2026 semua hak dilindungi undang-undang. | Data peta &copy; Kontributor OpenStreetMap</p>
                <div class="legal-links">
                    <a href="#">Kebijakan privasi</a> | 
                    <a href="#">Syarat dan Ketentuan</a> | 
                    <a href="#">Syarat dan Ketentuan Kartu Hadiah/Voucher</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    
    @auth
    <!-- Container untuk Toast Pop-up -->
    <div id="toastContainer" class="toast-container"></div>
    
    <!-- Script Real-Time Background Checking (Opsi A) -->
    <script>
        function launchConfettiShower() {
            const duration = 3.5 * 1000;
            const animationEnd = Date.now() + duration;
            const defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 1000000 };

            function randomInRange(min, max) {
                return Math.random() * (max - min) + min;
            }

            const interval = setInterval(function() {
                const timeLeft = animationEnd - Date.now();

                if (timeLeft <= 0) {
                    return clearInterval(interval);
                }

                const particleCount = 50 * (timeLeft / duration);
                confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 } }));
                confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 } }));
            }, 250);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Cek jika baru saja menyelesaikan pembayaran sukses
            if (sessionStorage.getItem('payment_success_confetti') === 'true') {
                sessionStorage.removeItem('payment_success_confetti');
                setTimeout(launchConfettiShower, 600);
            }

            // Dapatkan map status pesanan dari sessionStorage
            let orderStatusMap = JSON.parse(sessionStorage.getItem('orderStatusMap')) || {};
            let isFirstLoad = Object.keys(orderStatusMap).length === 0;

            function playChimeSound() {
                try {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (!AudioContext) return;
                    const ctx = new AudioContext();
                    
                    // Main tone (Chime)
                    const osc1 = ctx.createOscillator();
                    const gain1 = ctx.createGain();
                    osc1.type = 'sine';
                    osc1.frequency.setValueAtTime(880, ctx.currentTime); // A5 (high sweet pitch)
                    osc1.frequency.exponentialRampToValueAtTime(1760, ctx.currentTime + 0.1); // Sweet slide up
                    
                    gain1.gain.setValueAtTime(0.12, ctx.currentTime);
                    gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.7); // Smooth decay
                    
                    osc1.connect(gain1);
                    gain1.connect(ctx.destination);
                    osc1.start();
                    osc1.stop(ctx.currentTime + 0.7);
                    
                    // Second tone (harmonics / delayed ding)
                    setTimeout(() => {
                        const osc2 = ctx.createOscillator();
                        const gain2 = ctx.createGain();
                        osc2.type = 'sine';
                        osc2.frequency.setValueAtTime(1320, ctx.currentTime); // E6
                        osc2.frequency.exponentialRampToValueAtTime(2640, ctx.currentTime + 0.08);
                        
                        gain2.gain.setValueAtTime(0.08, ctx.currentTime);
                        gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
                        
                        osc2.connect(gain2);
                        gain2.connect(ctx.destination);
                        osc2.start();
                        osc2.stop(ctx.currentTime + 0.5);
                    }, 100);
                } catch(e) {
                    console.log('Audio playback error:', e);
                }
            }

            function checkOrderStatuses() {
                fetch('{{ route("order_status_check") }}')
                    .then(res => {
                        if (!res.ok) throw new Error('Network response was not ok');
                        return res.json();
                    })
                    .then(orders => {
                        let updatedMap = {};
                        orders.forEach(order => {
                            updatedMap[order.id] = order.status;
                            
                            // Jika bukan loading pertama, dan status lamanya ada tapi berubah
                            if (!isFirstLoad && orderStatusMap[order.id] && orderStatusMap[order.id] !== order.status) {
                                triggerToastNotification(order.id, order.status);
                            }
                        });
                        orderStatusMap = updatedMap;
                        sessionStorage.setItem('orderStatusMap', JSON.stringify(orderStatusMap));
                        isFirstLoad = false;
                    })
                    .catch(err => console.log('Checking status error:', err));
            }

            // Poller to check status changes
            checkOrderStatuses();
            setInterval(checkOrderStatuses, 12000);

            function triggerToastNotification(orderId, status) {
                playChimeSound(); // Bunyikan lonceng manis!
                
                const container = document.getElementById('toastContainer');
                if (!container) return;

                const toast = document.createElement('div');
                toast.className = 'toast-card';
                
                let icon = '📦';
                let title = 'Status Pesanan Terupdate';
                let desc = `Pesanan Kue #${orderId} Anda telah mengalami perubahan status.`;
                let progressColor = '#f06292';
                let glowColor = 'rgba(240, 98, 146, 0.25)';
                
                if (status === 'baru') {
                    icon = '⏳';
                    title = 'Menunggu Pembayaran';
                    desc = `Pesanan #${orderId} sukses dibuat. Silakan selesaikan pembayaran di simulator agar adonan dapat diproses.`;
                    progressColor = '#fbc02d';
                    glowColor = 'rgba(251, 192, 45, 0.25)';
                } else if (status === 'disiapkan') {
                    icon = '🥣';
                    title = 'Kue Sedang Dibuat! 🎂';
                    desc = `Chef terbaik kami sedang mengemas dan membuat adonan kue lezat pesanan #${orderId} Anda dengan higienis!`;
                    progressColor = '#e91e63';
                    glowColor = 'rgba(233, 30, 99, 0.25)';
                } else if (status === 'dikirim') {
                    icon = '🛵';
                    title = 'Pesanan Dikirim! 🚀';
                    desc = `Kue manis pesanan #${orderId} Anda sedang diantar kurir ke alamat tujuan. Bersiap-siap ya!`;
                    progressColor = '#2196f3';
                    glowColor = 'rgba(33, 150, 243, 0.25)';
                } else if (status === 'selesai') {
                    icon = '✅';
                    title = 'Pesanan Selesai! ❤️';
                    desc = `Terima kasih! Pesanan #${orderId} Anda telah sukses diterima. Selamat menikmati hidangan manis kami!`;
                    progressColor = '#4caf50';
                    glowColor = 'rgba(76, 175, 80, 0.25)';
                } else if (status === 'dibatalkan') {
                    icon = '❌';
                    title = 'Pesanan Dibatalkan';
                    desc = `Pesanan #${orderId} Anda telah dibatalkan oleh pihak toko.`;
                    progressColor = '#f44336';
                    glowColor = 'rgba(244, 67, 54, 0.25)';
                }
                
                toast.style.boxShadow = `0 15px 40px ${glowColor}`;
                toast.style.borderLeft = `6px solid ${progressColor}`;
                toast.innerHTML = `
                    <div style="font-size: 32px; background: #fff5f5; border-radius: 16px; width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; box-shadow: 0 6px 15px rgba(0,0,0,0.03); flex-shrink: 0; animation: bounceNotif 1s infinite alternate;">
                        ${icon}
                    </div>
                    <div style="flex: 1; padding-right: 8px;">
                        <div style="font-weight: 900; font-size: 16px; color: #6d4c41; margin-bottom: 4px;">${title}</div>
                        <div style="font-size: 13.5px; color: #8d6e63; line-height: 1.5; font-weight: 600;">${desc}</div>
                    </div>
                    <button onclick="this.parentElement.remove()" style="background: rgba(0,0,0,0.04); border: none; font-size: 18px; color: #bcaaa4; cursor: pointer; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s; flex-shrink: 0;" onmouseover="this.style.background='#ffebee'; this.style.color='#f06292'" onmouseout="this.style.background='rgba(0,0,0,0.04)'; this.style.color='#bcaaa4'">&times;</button>
                    <div class="toast-progress">
                        <div class="toast-progress-bar" id="progressBar-${orderId}" style="background: ${progressColor}; width: 100%;"></div>
                    </div>
                `;
                
                toast.addEventListener('click', function(e) {
                    if (e.target.tagName.toLowerCase() === 'button' || e.target.closest('button')) {
                        return;
                    }
                    window.location.href = '{{ route("notifikasi") }}';
                });

                container.appendChild(toast);
                
                // Pemicu animasi slide-in
                setTimeout(() => toast.classList.add('show'), 150);
                
                // Pemicu linear progress bar menyusut
                setTimeout(() => {
                    const pBar = document.getElementById(`progressBar-${orderId}`);
                    if (pBar) pBar.style.width = '0%';
                }, 200);
                
                // Otomatis hilangkan dalam 8 detik
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 600);
                }, 8000);
            }
        });
    </script>
    @endauth

    @stack('scripts')

    <!-- Motion Effects: Scroll Reveal, Parallax, Stagger -->
    <script>
        // Scroll Reveal
        const observerOptions = { threshold: 0.12, rootMargin: '0px 0px -60px 0px' };
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, observerOptions);

        document.querySelectorAll('.scroll-reveal').forEach(el => revealObserver.observe(el));

        // Stagger containers (reveal children one by one)
        const staggerObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('stagger-revealed');
                    staggerObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

        document.querySelectorAll('.stagger').forEach(el => staggerObserver.observe(el));

        // Stagger children animation class
        const style = document.createElement('style');
        style.textContent = `
            .stagger > * {
                opacity: 0;
                transform: translateY(20px);
                animation: none;
            }
            .stagger-revealed > * {
                opacity: 1;
                transform: translateY(0);
                animation: staggerUp 0.5s ease-out forwards;
            }
            .stagger-revealed > *:nth-child(1) { animation-delay: 0.05s; }
            .stagger-revealed > *:nth-child(2) { animation-delay: 0.1s; }
            .stagger-revealed > *:nth-child(3) { animation-delay: 0.15s; }
            .stagger-revealed > *:nth-child(4) { animation-delay: 0.2s; }
            .stagger-revealed > *:nth-child(5) { animation-delay: 0.25s; }
            .stagger-revealed > *:nth-child(6) { animation-delay: 0.3s; }
            .stagger-revealed > *:nth-child(7) { animation-delay: 0.35s; }
            .stagger-revealed > *:nth-child(8) { animation-delay: 0.4s; }
            @keyframes staggerUp {
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(style);

        // Parallax image on mouse move
        document.querySelectorAll('.parallax-img').forEach(img => {
            const container = img.closest('.parallax-container') || img.parentElement;
            container.addEventListener('mousemove', (e) => {
                const rect = container.getBoundingClientRect();
                const x = (e.clientX - rect.left) / rect.width - 0.5;
                const y = (e.clientY - rect.top) / rect.height - 0.5;
                img.style.transform = `translate(${x * 15}px, ${y * 15}px)`;
            });
            container.addEventListener('mouseleave', () => {
                img.style.transform = 'translate(0, 0)';
                img.style.transition = 'transform 0.5s ease';
            });
        });

        // Button press ripple
        document.querySelectorAll('.btn-press').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const ripple = document.createElement('span');
                const rect = btn.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');
                btn.appendChild(ripple);
                setTimeout(() => ripple.remove(), 600);
            });
        });

        const rippleStyle = document.createElement('style');
        rippleStyle.textContent = `
            .btn-press { position: relative; overflow: hidden; }
            .ripple {
                position: absolute;
                border-radius: 50%;
                background: rgba(255,255,255,0.4);
                transform: scale(0);
                animation: rippleAnim 0.6s linear;
                pointer-events: none;
            }
            @keyframes rippleAnim {
                to { transform: scale(4); opacity: 0; }
            }
        `;
        document.head.appendChild(rippleStyle);
    </script>

    {{-- ================= Chatbot penjualan (24/7) ================= --}}
    <div id="chatWidget" class="chat-widget">
        <button id="chatToggle" class="chat-fab" type="button" aria-label="Buka chat bantuan">
            <span aria-hidden="true">💬</span>
            <span id="chatBadge" class="chat-badge">1</span>
        </button>

        <section id="chatPanel" class="chat-panel" aria-hidden="true" aria-label="Chat bantuan">
            <header class="chat-header">
                <div class="chat-avatar" aria-hidden="true">🍰</div>
                <div class="chat-heading">
                    <div class="chat-title">Seana Assistant</div>
                    <div class="chat-status"><span class="chat-dot"></span> Online 24/7</div>
                </div>
                <button id="chatClose" class="chat-close" type="button" aria-label="Tutup chat">&times;</button>
            </header>

            <div id="chatBody" class="chat-body" role="log" aria-live="polite"></div>
            <div id="chatChips" class="chat-chips"></div>

            <form id="chatForm" class="chat-form" autocomplete="off">
                <input id="chatInput" type="text" name="message" maxlength="500" placeholder="Tulis pertanyaanmu..." aria-label="Pesan">
                <button type="submit" class="chat-send" aria-label="Kirim">➤</button>
            </form>
        </section>
    </div>

    <script>
    (function () {
        const widget = document.getElementById('chatWidget');
        const toggle = document.getElementById('chatToggle');
        const panel  = document.getElementById('chatPanel');
        const close  = document.getElementById('chatClose');
        const body   = document.getElementById('chatBody');
        const chips  = document.getElementById('chatChips');
        const form   = document.getElementById('chatForm');
        const input  = document.getElementById('chatInput');
        const badge  = document.getElementById('chatBadge');

        if (!widget || !toggle || !panel) return;

        // Jika halaman punya tombol keranjang mengambang, naikkan posisi chat.
        if (document.querySelector('.floating-cart')) {
            widget.classList.add('chat-above-cart');
        }

        const endpoint = @json(route('chatbot.reply'));
        const meta = document.querySelector('meta[name="csrf-token"]');
        const csrf = meta ? meta.content : '';
        let started = false;
        let busy = false;

        function scrollBottom() {
            body.scrollTop = body.scrollHeight;
        }

        function addMessage(text, who) {
            const row = document.createElement('div');
            row.className = 'chat-msg chat-msg-' + who;

            const bubble = document.createElement('div');
            bubble.className = 'chat-bubble';
            bubble.textContent = text;

            row.appendChild(bubble);
            body.appendChild(row);
            scrollBottom();
            return row;
        }

        function addTyping() {
            const row = document.createElement('div');
            row.className = 'chat-msg chat-msg-bot chat-typing';
            row.innerHTML = '<div class="chat-bubble"><span></span><span></span><span></span></div>';
            body.appendChild(row);
            scrollBottom();
            return row;
        }

        function renderChips(list) {
            chips.innerHTML = '';
            (list || []).slice(0, 5).forEach(function (label) {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'chat-chip';
                chip.textContent = label;
                chip.addEventListener('click', function () { send(label); });
                chips.appendChild(chip);
            });
            scrollBottom();
        }

        async function send(message) {
            const text = String(message || '').trim();
            if (!text || busy) return;

            busy = true;
            addMessage(text, 'user');
            input.value = '';
            chips.innerHTML = '';
            const typing = addTyping();

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ message: text })
                });

                if (!res.ok) throw new Error('HTTP ' + res.status);

                const contentType = res.headers.get('content-type') || '';
                if (contentType.indexOf('application/json') === -1) {
                    throw new Error('Respons bukan JSON');
                }

                const data = await res.json();
                typing.remove();
                addMessage(data.reply || 'Maaf, saya belum bisa menjawab saat ini.', 'bot');
                renderChips(data.quick_replies || []);
            } catch (err) {
                typing.remove();
                if (window.console && console.warn) console.warn('Chatbot gagal:', err && err.message);
                addMessage('Koneksi sedang bermasalah. Coba lagi sebentar, atau hubungi admin lewat WhatsApp.', 'bot');
            } finally {
                busy = false;
                input.focus();
            }
        }

        function openPanel() {
            panel.classList.add('is-open');
            panel.setAttribute('aria-hidden', 'false');
            widget.classList.add('is-open');
            if (badge) badge.style.display = 'none';

            if (!started) {
                started = true;
                addMessage('Halo! 👋 Saya Seana Assistant, siap bantu 24/7.\n\nTanyakan harga produk, minta rekomendasi, cek ongkir, atau tanya cara pesan.', 'bot');
                renderChips(['Lihat menu', 'Rekomendasi', 'Ongkir', 'Cara pesan']);
            }
            setTimeout(function () { input.focus(); }, 260);
        }

        function closePanel() {
            panel.classList.remove('is-open');
            panel.setAttribute('aria-hidden', 'true');
            widget.classList.remove('is-open');
        }

        toggle.addEventListener('click', function () {
            if (panel.classList.contains('is-open')) {
                closePanel();
            } else {
                openPanel();
            }
        });

        close.addEventListener('click', closePanel);

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            send(input.value);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && panel.classList.contains('is-open')) closePanel();
        });

        // Bisa dipanggil dari tombol lain di halaman.
        window.seanaChat = {
            open: openPanel,
            send: function (message) { openPanel(); send(message); }
        };
    })();
    </script>

    {{-- Navbar floating pill: bayangan saat halaman di-scroll, mengecil jadi
         ikon saja ketika scroll ke bawah, dan melebar lagi ketika diklik. --}}
    <script>
    (function () {
        var bar = document.querySelector('.topbar');
        if (!bar) return;

        var expandBtn = document.getElementById('navExpandBtn');
        var lastY = window.scrollY;
        var expandedByUser = false;
        var expandedAtY = 0;

        function apply(collapsed) {
            bar.classList.toggle('is-collapsed', collapsed);
        }

        function sync() {
            var y = window.scrollY;

            // Kembali ke paling atas: navbar selalu penuh lagi.
            if (y <= 60) {
                expandedByUser = false;
                apply(false);
            } else if (expandedByUser && y - expandedAtY > 150) {
                // Setelah dibuka user, biarkan terbuka sampai ia lanjut scroll
                // cukup jauh, baru boleh mengecil lagi.
                expandedByUser = false;
            }

            // Hanya mengecil saat scroll ke bawah — tidak pernah membuka sendiri,
            // supaya tidak terjadi buka-tutup berulang (oscillation).
            if (!expandedByUser && y > 200 && y > lastY) {
                apply(true);
            }

            bar.classList.toggle('topbar-scrolled', y > 8);

            lastY = y;
        }

        function expand(e) {
            if (!bar.classList.contains('is-collapsed')) return;
            if (e) e.preventDefault();
            expandedByUser = true;
            expandedAtY = window.scrollY;
            apply(false);
            lastY = window.scrollY;
        }

        if (expandBtn) {
            expandBtn.addEventListener('click', expand);
        }

        // Klik area kosong navbar juga melebarkannya kembali.
        bar.addEventListener('click', function (e) {
            if (e.target.closest('a, ul, .nav-links, .btn-pesan')) return;
            expand(e);
        });

        sync();
        window.addEventListener('scroll', sync, { passive: true });
    })();
    </script>
</body>
</html>
