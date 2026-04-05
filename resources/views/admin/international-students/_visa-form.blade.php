<form method="POST" action="{{ route('admin.international-students.store-visa', $student) }}" enctype="multipart/form-data" onkeydown="if(event.key==='Enter'&&event.target.tagName!=='BUTTON'){event.preventDefault();}">
    @csrf
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px;">
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Pasport raqami</label>
            <input type="text" name="passport_number" value="{{ $visaInfo?->passport_number ?? '' }}" oninput="this.value=this.value.toUpperCase()" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;text-transform:uppercase;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Pasport berilgan joy</label>
            <input type="text" name="passport_issued_place" value="{{ $visaInfo?->passport_issued_place ?? '' }}" oninput="this.value=this.value.toUpperCase()" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;text-transform:uppercase;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Pasport berilgan sana</label>
            <input type="date" name="passport_issued_date" value="{{ $visaInfo?->passport_issued_date?->format('Y-m-d') ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;cursor:pointer;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Pasport tugash sanasi</label>
            <input type="date" name="passport_expiry_date" value="{{ $visaInfo?->passport_expiry_date?->format('Y-m-d') ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;cursor:pointer;">
        </div>
    </div>

    <div style="font-size:11px;font-weight:600;color:#94a3b8;margin:14px 0 8px;text-transform:uppercase;">Tug'ilgan joy</div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
        <div x-data="adminCountrySelect({ value: '{{ $visaInfo?->birth_country ?? 'India' }}' })">
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Davlat</label>
            <div style="position:relative;">
                <input type="text" x-model="search" @focus="open=true" @click="open=true" @input="open=true" @keydown.enter.prevent="if(filtered.length>0){value=filtered[0];search=filtered[0];open=false;$dispatch('country-changed',{country:filtered[0]})}" placeholder="Qidiring..." style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;" autocomplete="off">
                <input type="hidden" name="birth_country" :value="value">
                <div x-show="open && filtered.length > 0" @click.away="open=false" x-transition style="position:absolute;z-index:50;width:100%;margin-top:2px;background:#fff;border:1px solid #e2e8f0;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.1);max-height:180px;overflow-y:auto;">
                    <template x-for="item in filtered" :key="item">
                        <div @click="value=item;search=item;open=false;$dispatch('country-changed',{country:item})" x-text="item" style="padding:6px 10px;font-size:11px;cursor:pointer;" onmouseover="this.style.background='#eef2ff'" onmouseout="this.style.background='#fff'"></div>
                    </template>
                </div>
            </div>
        </div>
        <div x-data="adminRegionSelect({ value: '{{ $visaInfo?->birth_region ?? '' }}', country: '{{ $visaInfo?->birth_country ?? 'India' }}' })" @country-changed.window="country=$event.detail.country;search='';value=''">
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Viloyat</label>
            <div style="position:relative;">
                <input type="text" x-model="search" @focus="open=true" @click="open=true" @input="open=true" @keydown.enter.prevent="if(filtered.length>0){value=filtered[0];search=filtered[0];open=false}" placeholder="Qidiring..." style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;" autocomplete="off">
                <input type="hidden" name="birth_region" :value="value">
                <div x-show="open && filtered.length > 0" @click.away="open=false" x-transition style="position:absolute;z-index:50;width:100%;margin-top:2px;background:#fff;border:1px solid #e2e8f0;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.1);max-height:180px;overflow-y:auto;">
                    <template x-for="item in filtered" :key="item">
                        <div @click="value=item;search=item;open=false;" x-text="item" style="padding:6px 10px;font-size:11px;cursor:pointer;" onmouseover="this.style.background='#eef2ff'" onmouseout="this.style.background='#fff'"></div>
                    </template>
                </div>
            </div>
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Shahar</label>
            <input type="text" name="birth_city" value="{{ $visaInfo?->birth_city ?? '' }}" oninput="this.value=this.value.toUpperCase()" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;text-transform:uppercase;">
        </div>
    </div>

    <div style="font-size:11px;font-weight:600;color:#94a3b8;margin:14px 0 8px;text-transform:uppercase;">Registratsiya</div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Boshlanish</label>
            <input type="date" name="registration_start_date" value="{{ $visaInfo?->registration_start_date?->format('Y-m-d') ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;cursor:pointer;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Tugash</label>
            <input type="date" name="registration_end_date" value="{{ $visaInfo?->registration_end_date?->format('Y-m-d') ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;cursor:pointer;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Kirish sanasi</label>
            <input type="date" name="entry_date" value="{{ $visaInfo?->entry_date?->format('Y-m-d') ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;cursor:pointer;">
        </div>
    </div>

    <div style="font-size:11px;font-weight:600;color:#94a3b8;margin:14px 0 8px;text-transform:uppercase;">Viza</div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;">
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Viza raqami</label>
            <input type="text" name="visa_number" value="{{ $visaInfo?->visa_number ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Viza turi</label>
            <select name="visa_type" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
                <option value="">-</option>
                @foreach(\App\Models\StudentVisaInfo::VISA_TYPES as $k => $l)
                    <option value="{{ $k }}" {{ ($visaInfo?->visa_type ?? 'A-1') === $k ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Kirishlar</label>
            <input type="number" name="visa_entries_count" value="{{ $visaInfo?->visa_entries_count ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Muddat (kun)</label>
            <input type="number" name="visa_stay_days" value="{{ $visaInfo?->visa_stay_days ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;margin-top:10px;">
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Boshlanish</label>
            <input type="date" name="visa_start_date" value="{{ $visaInfo?->visa_start_date?->format('Y-m-d') ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;cursor:pointer;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Tugash</label>
            <input type="date" name="visa_end_date" value="{{ $visaInfo?->visa_end_date?->format('Y-m-d') ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;cursor:pointer;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Berilgan joy</label>
            <input type="text" name="visa_issued_place" value="{{ $visaInfo?->visa_issued_place ?? '' }}" oninput="this.value=this.value.toUpperCase()" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;text-transform:uppercase;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Berilgan sana</label>
            <input type="date" name="visa_issued_date" value="{{ $visaInfo?->visa_issued_date?->format('Y-m-d') ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;cursor:pointer;">
        </div>
    </div>

    <div style="font-size:11px;font-weight:600;color:#94a3b8;margin:14px 0 8px;text-transform:uppercase;">Hujjatlar (PDF)</div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Pasport skaneri</label>
            @if($visaInfo?->passport_scan_path)
                <div style="margin-bottom:4px;"><a href="{{ route('admin.international-students.file', [$student, 'passport_scan_path']) }}" target="_blank" style="font-size:10px;color:#4f46e5;">Yuklangan faylni ko'rish</a></div>
            @endif
            <input type="file" name="passport_scan" accept=".pdf" style="width:100%;font-size:11px;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Viza skaneri</label>
            @if($visaInfo?->visa_scan_path)
                <div style="margin-bottom:4px;"><a href="{{ route('admin.international-students.file', [$student, 'visa_scan_path']) }}" target="_blank" style="font-size:10px;color:#4f46e5;">Yuklangan faylni ko'rish</a></div>
            @endif
            <input type="file" name="visa_scan" accept=".pdf" style="width:100%;font-size:11px;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Registratsiya hujjati</label>
            @if($visaInfo?->registration_doc_path)
                <div style="margin-bottom:4px;"><a href="{{ route('admin.international-students.file', [$student, 'registration_doc_path']) }}" target="_blank" style="font-size:10px;color:#4f46e5;">Yuklangan faylni ko'rish</a></div>
            @endif
            <input type="file" name="registration_doc" accept=".pdf" style="width:100%;font-size:11px;">
        </div>
    </div>

    <div style="margin-top:14px;display:flex;justify-content:flex-end;">
        <button type="submit" style="padding:8px 20px;font-size:12px;font-weight:600;background:#4f46e5;color:#fff;border:none;border-radius:8px;cursor:pointer;">Saqlash</button>
    </div>
</form>

<script>
if (typeof adminCountrySelect === 'undefined') {
    var adminCountryItems = ['Afghanistan','Albania','Algeria','Angola','Argentina','Armenia','Australia','Austria','Azerbaijan','Bahrain','Bangladesh','Belarus','Belgium','Bhutan','Bolivia','Bosnia','Brazil','Brunei','Bulgaria','Cambodia','Cameroon','Canada','Chad','Chile','China','Colombia','Congo','Croatia','Cuba','Cyprus','Czech Republic','Denmark','Ecuador','Egypt','Eritrea','Estonia','Ethiopia','Finland','France','Georgia','Germany','Ghana','Greece','Guatemala','Guinea','Haiti','Honduras','Hungary','India','Indonesia','Iran','Iraq','Ireland','Israel','Italy','Jamaica','Japan','Jordan','Kazakhstan','Kenya','Korea','Kuwait','Kyrgyzstan','Laos','Latvia','Lebanon','Libya','Lithuania','Madagascar','Malaysia','Maldives','Mali','Mexico','Moldova','Mongolia','Morocco','Mozambique','Myanmar','Nepal','Netherlands','New Zealand','Nicaragua','Niger','Nigeria','Norway','Oman','Pakistan','Palestine','Panama','Paraguay','Peru','Philippines','Poland','Portugal','Qatar','Romania','Russia','Saudi Arabia','Senegal','Serbia','Singapore','Slovakia','Slovenia','Somalia','South Africa','Spain','Sri Lanka','Sudan','Sweden','Switzerland','Syria','Tajikistan','Tanzania','Thailand','Tunisia','Turkey','Turkmenistan','UAE','Uganda','Ukraine','United Kingdom','United States','Uruguay','Uzbekistan','Venezuela','Vietnam','Yemen','Zambia','Zimbabwe'];
    window.adminCountrySelect = function(config) {
        return {
            items: adminCountryItems,
            value: config.value || '',
            search: config.value || '',
            open: false,
            get filtered() {
                if (!this.search) return this.items;
                var q = this.search.toLowerCase();
                return this.items.filter(function(i) { return i.toLowerCase().includes(q); });
            }
        };
    };
}
if (typeof adminRegionSelect === 'undefined') {
    var adminCountryRegions = {
        'India':['Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Delhi','Goa','Gujarat','Haryana','Himachal Pradesh','Jammu and Kashmir','Jharkhand','Karnataka','Kerala','Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura','Uttar Pradesh','Uttarakhand','West Bengal'],
        'Pakistan':['Balochistan','Islamabad','Khyber Pakhtunkhwa','Punjab','Sindh','Azad Kashmir','Gilgit-Baltistan'],
        'Bangladesh':['Barishal','Chattogram','Dhaka','Khulna','Mymensingh','Rajshahi','Rangpur','Sylhet'],
        'Afghanistan':['Badakhshan','Balkh','Bamyan','Herat','Kabul','Kandahar','Kunduz','Nangarhar','Takhar'],
        'Tajikistan':['Dushanbe','Sughd','Khatlon','Gorno-Badakhshan'],
        'Turkmenistan':['Ahal','Balkan','Dashoguz','Lebap','Mary','Ashgabat'],
        'Kyrgyzstan':['Batken','Chuy','Issyk-Kul','Jalal-Abad','Naryn','Osh','Talas','Bishkek'],
        'Kazakhstan':['Almaty','Astana','Shymkent','Akmola','Aktobe','Atyrau','East Kazakhstan','Karaganda','Kostanay','Turkistan'],
        'China':['Beijing','Guangdong','Jiangsu','Shandong','Shanghai','Sichuan','Zhejiang'],
        'Nepal':['Province No. 1','Madhesh','Bagmati','Gandaki','Lumbini','Karnali','Sudurpashchim'],
        'Sri Lanka':['Central','Eastern','Northern','Southern','Western'],
        'Indonesia':['Bali','Jakarta','West Java','East Java','Central Java'],
        'Nigeria':['Abuja','Lagos','Kano','Kaduna','Rivers'],
    };
    window.adminRegionSelect = function(config) {
        return {
            country: config.country || '',
            value: config.value || '',
            search: config.value || '',
            open: false,
            get regions() {
                return adminCountryRegions[this.country] || [];
            },
            get filtered() {
                var r = this.regions;
                if (r.length === 0) return [];
                if (!this.search) return r;
                var q = this.search.toLowerCase();
                return r.filter(function(i) { return i.toLowerCase().includes(q); });
            }
        };
    };
}
</script>
