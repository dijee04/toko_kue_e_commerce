@extends('user.layouts.app')

@section('content')
<div class="container" style="max-width: 800px; margin: 40px auto; padding: 0 20px;">
    
    @if(session('success'))
        <div class="alert" style="background: #e8f5e9; color: #2e7d32; padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px; border-left: 5px solid #4caf50;">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    <div class="card" style="background: white; border-radius: 24px; box-shadow: 0 10px 30px rgba(240, 98, 146, 0.1); overflow: hidden; border: 1px solid #ffe0d0;">
        <div style="background: linear-gradient(135deg, #fce4ec, #f8bbd0); padding: 40px 30px; text-align: center;">
            <div style="width: 100px; height: 100px; background: white; border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; font-size: 40px; color: #f06292; box-shadow: 0 8px 20px rgba(240, 98, 146, 0.2);">
                <i class="fas fa-user-circle"></i>
            </div>
            <h2 style="margin: 0; color: #880e4f; font-weight: 800; font-size: 24px;">Profil Saya</h2>
            <p style="margin: 5px 0 0; color: #c2185b; font-size: 14px;">Kelola informasi pribadi Anda</p>
        </div>
        
        <div style="padding: 40px 30px;">
            <form action="{{ route('profil.update') }}" method="POST">
                @csrf
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 700; color: #6d4c41; margin-bottom: 8px; font-size: 14px;">Nama Lengkap</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required style="width: 100%; padding: 14px 18px; border-radius: 12px; border: 2px solid #ffe0d0; outline: none; font-family: inherit; font-size: 15px; transition: all 0.3s;" onfocus="this.style.borderColor='#f06292'" onblur="this.style.borderColor='#ffe0d0'">
                    @error('name') <span style="color: #e53935; font-size: 12px; margin-top: 5px; display: block;">{{ $message }}</span> @enderror
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 700; color: #6d4c41; margin-bottom: 8px; font-size: 14px;">Email</label>
                    <input type="email" value="{{ $user->email }}" readonly style="width: 100%; padding: 14px 18px; border-radius: 12px; border: 2px solid #eee; outline: none; font-family: inherit; font-size: 15px; background: #f9f9f9; color: #999; cursor: not-allowed;">
                    <small style="color: #999; font-size: 11px; margin-top: 5px; display: block;">Email digunakan untuk login dan tidak dapat diubah.</small>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 700; color: #6d4c41; margin-bottom: 8px; font-size: 14px;">Nomor WhatsApp</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #25D366; font-size: 18px;"><i class="fab fa-whatsapp"></i></span>
                        <input type="text" name="no_wa" value="{{ old('no_wa', $user->no_wa) }}" placeholder="Contoh: 08123456789" style="width: 100%; padding: 14px 18px 14px 45px; border-radius: 12px; border: 2px solid #ffe0d0; outline: none; font-family: inherit; font-size: 15px; transition: all 0.3s;" onfocus="this.style.borderColor='#f06292'" onblur="this.style.borderColor='#ffe0d0'">
                    </div>
                    @error('no_wa') <span style="color: #e53935; font-size: 12px; margin-top: 5px; display: block;">{{ $message }}</span> @enderror
                </div>

                <div style="margin-bottom: 30px;">
                    <label style="display: block; font-weight: 700; color: #6d4c41; margin-bottom: 8px; font-size: 14px;">Alamat Pengiriman Default</label>
                    <textarea name="alamat" rows="4" placeholder="Alamat lengkap untuk pengiriman kue..." style="width: 100%; padding: 14px 18px; border-radius: 12px; border: 2px solid #ffe0d0; outline: none; font-family: inherit; font-size: 15px; transition: all 0.3s; resize: vertical;" onfocus="this.style.borderColor='#f06292'" onblur="this.style.borderColor='#ffe0d0'">{{ old('alamat', $user->alamat) }}</textarea>
                    @error('alamat') <span style="color: #e53935; font-size: 12px; margin-top: 5px; display: block;">{{ $message }}</span> @enderror
                </div>

                <div style="margin-bottom: 30px;">
                    <label style="display: block; font-weight: 700; color: #6d4c41; margin-bottom: 8px; font-size: 14px;">Titik Koordinat Lokasi (Maps)</label>
                    <p style="margin: 0 0 10px; color: #999; font-size: 12px;">Geser penanda atau klik pada peta untuk menentukan titik lokasi pengiriman Anda. Titik ini dipakai sebagai tujuan pengantaran saat checkout.</p>
                    <div id="profileMap" style="height: 320px; border-radius: 16px; border: 2px solid #ffe0d0; overflow: hidden; position: relative; z-index: 0; isolation: isolate;"></div>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 10px;">
                        <button type="button" id="useMyLocationBtn" style="padding: 10px 18px; border-radius: 50px; border: none; background: #e0f2f1; color: #00695c; font-weight: 700; font-size: 13px; cursor: pointer;">
                            📍 Gunakan Lokasi Saya
                        </button>
                        <button type="button" id="clearLocationBtn" style="padding: 10px 18px; border-radius: 50px; border: 1px solid #ffcdd2; background: white; color: #e57373; font-weight: 700; font-size: 13px; cursor: pointer;">
                            ✖ Hapus Titik
                        </button>
                        <span id="coordReadout" style="font-size: 12.5px; color: #8d6e63; font-weight: 700;"></span>
                    </div>
                    <input type="hidden" name="latitude" id="latInput" value="{{ old('latitude', $user->latitude) }}">
                    <input type="hidden" name="longitude" id="lngInput" value="{{ old('longitude', $user->longitude) }}">
                    @error('latitude') <span style="color: #e53935; font-size: 12px; margin-top: 5px; display: block;">{{ $message }}</span> @enderror
                    @error('longitude') <span style="color: #e53935; font-size: 12px; margin-top: 5px; display: block;">{{ $message }}</span> @enderror
                </div>

                <div style="display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="submit" style="padding: 14px 30px; border-radius: 12px; border: none; background: #f06292; color: white; font-weight: 800; font-size: 15px; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 15px rgba(240, 98, 146, 0.3);">
                        <i class="fas fa-save" style="margin-right: 8px;"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
            
            <hr style="border: none; border-top: 1px solid #ffe0d0; margin: 40px 0;">
            
            <div style="background: #fff5f5; border: 1px solid #ffcdd2; border-radius: 16px; padding: 25px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0 0 5px 0; color: #c62828; font-size: 16px;">Keluar dari Akun</h3>
                    <p style="margin: 0; color: #e57373; font-size: 13px;">Anda akan membutuhkan email dan kata sandi untuk masuk kembali.</p>
                </div>
                <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" style="padding: 12px 25px; border-radius: 12px; background: white; border: 2px solid #ef5350; color: #ef5350; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s;" onmouseover="this.style.background='#ef5350'; this.style.color='white'" onmouseout="this.style.background='white'; this.style.color='#ef5350'">
                    <i class="fas fa-sign-out-alt"></i> Keluar Akun
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                    @csrf
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const RESTO_LAT = -6.1872, RESTO_LNG = 106.8491;
    const latInput = document.getElementById('latInput');
    const lngInput = document.getElementById('lngInput');
    const readout = document.getElementById('coordReadout');
    if (!document.getElementById('profileMap')) return;

    const hasSaved = latInput.value !== '' && lngInput.value !== ''
        && !isNaN(parseFloat(latInput.value)) && !isNaN(parseFloat(lngInput.value));
    const startLat = hasSaved ? parseFloat(latInput.value) : RESTO_LAT;
    const startLng = hasSaved ? parseFloat(lngInput.value) : RESTO_LNG;

    const map = L.map('profileMap').setView([startLat, startLng], hasSaved ? 15 : 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    let marker = null;

    function updateInputs(lat, lng) {
        latInput.value = lat.toFixed(7);
        lngInput.value = lng.toFixed(7);
        readout.textContent = 'Lat: ' + lat.toFixed(6) + ', Lng: ' + lng.toFixed(6);
    }

    function setMarker(lat, lng) {
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', function () {
                const p = marker.getLatLng();
                updateInputs(p.lat, p.lng);
            });
        }
        updateInputs(lat, lng);
    }

    if (hasSaved) {
        setMarker(startLat, startLng);
    } else {
        readout.textContent = 'Belum ada titik dipilih.';
    }

    map.on('click', function (e) {
        setMarker(e.latlng.lat, e.latlng.lng);
    });

    document.getElementById('useMyLocationBtn').addEventListener('click', function () {
        if (!navigator.geolocation) { alert('Browser tidak mendukung geolokasi.'); return; }
        const btn = this;
        btn.textContent = '⏳ Mencari...';
        navigator.geolocation.getCurrentPosition(function (pos) {
            setMarker(pos.coords.latitude, pos.coords.longitude);
            map.setView([pos.coords.latitude, pos.coords.longitude], 16);
            btn.textContent = '📍 Gunakan Lokasi Saya';
        }, function () {
            alert('Gagal mendapatkan lokasi. Pastikan izin lokasi diaktifkan.');
            btn.textContent = '📍 Gunakan Lokasi Saya';
        });
    });

    document.getElementById('clearLocationBtn').addEventListener('click', function () {
        latInput.value = '';
        lngInput.value = '';
        if (marker) { map.removeLayer(marker); marker = null; }
        readout.textContent = 'Belum ada titik dipilih.';
    });
});
</script>
@endpush
