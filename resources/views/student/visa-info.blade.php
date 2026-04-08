<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __('Viza ma\'lumotlarim') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3 pb-6">
        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Status badge --}}
        @if($visaInfo)
            <div class="mb-4">
                @if($visaInfo->status === 'approved')
                    <div class="p-4 bg-green-50 border border-green-200 rounded-lg flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                        <span class="text-sm font-medium text-green-700">{{ __('Ma\'lumotlaringiz tasdiqlangan') }}</span>
                    </div>
                @elseif($visaInfo->status === 'rejected')
                    <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-center gap-2 mb-1">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <span class="text-sm font-medium text-red-700">{{ __('Ma\'lumotlaringiz rad etilgan') }}</span>
                        </div>
                        @if($visaInfo->rejection_reason)
                            <p class="text-sm text-red-600 ml-7">{{ __('Sabab') }}: {{ $visaInfo->rejection_reason }}</p>
                        @endif
                    </div>
                @elseif($visaInfo->status === 'pending')
                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg flex items-center gap-2">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm font-medium text-yellow-700">{{ __('Ma\'lumotlaringiz tekshirilmoqda') }}</span>
                    </div>
                @endif
            </div>
        @endif

        <div class="bg-white shadow rounded-lg p-5 border border-gray-200" x-data="{ showForm: {{ ($errors->any() || !$visaInfo || $visaInfo->status === 'rejected') ? 'true' : 'false' }} }">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z"/>
                    </svg>
                    <h4 class="text-lg font-semibold text-gray-800">{{ __('Viza ma\'lumotlarim') }}</h4>
                </div>

                @if($visaInfo && $visaInfo->status !== 'rejected')
                    <button @click="showForm = !showForm" type="button" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                        <span x-text="showForm ? 'Yopish' : 'Ko\'rish / Tahrirlash'"></span>
                    </button>
                @endif
            </div>

            {{-- Agar ma'lumotlar tasdiqlangan bo'lsa, faqat ko'rsatish --}}
            @if($visaInfo && $visaInfo->status === 'approved')
                <div x-show="showForm" x-transition>
                    @include('student.partials.visa-info-readonly')
                </div>
            @else
                <div x-show="showForm" x-transition>
                    @include('student.partials.visa-info-form')
                </div>
            @endif
        </div>
    </div>

<script>
function checkPdfSize(input) {
    var errorEl = input.closest('div').querySelector('[data-file-error]');
    if (!errorEl) return;
    if (input.files.length > 0 && input.files[0].size > 5 * 1024 * 1024) {
        errorEl.textContent = '{{ __("Fayl hajmi 5MB dan oshmasligi kerak!") }}';
        errorEl.classList.remove('hidden');
        input.value = '';
    } else {
        errorEl.textContent = '';
        errorEl.classList.add('hidden');
    }
}
</script>

{{-- Alpine store + searchable select components --}}
<script>
document.addEventListener('alpine:init', function() {
    Alpine.store('birthCountry', '{{ old('birth_country', $visaInfo?->birth_country ?? 'India') }}');
});

var countryRegions = {
    'India': ['Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Delhi','Goa','Gujarat','Haryana','Himachal Pradesh','Jammu and Kashmir','Jharkhand','Karnataka','Kerala','Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura','Uttar Pradesh','Uttarakhand','West Bengal'],
    'Pakistan': ['Balochistan','Islamabad','Khyber Pakhtunkhwa','Punjab','Sindh','Azad Kashmir','Gilgit-Baltistan'],
    'Bangladesh': ['Barishal','Chattogram','Dhaka','Khulna','Mymensingh','Rajshahi','Rangpur','Sylhet'],
    'Afghanistan': ['Badakhshan','Badghis','Baghlan','Balkh','Bamyan','Daykundi','Farah','Faryab','Ghazni','Ghor','Helmand','Herat','Jowzjan','Kabul','Kandahar','Kapisa','Khost','Kunar','Kunduz','Laghman','Logar','Nangarhar','Nimroz','Nuristan','Paktia','Paktika','Panjshir','Parwan','Samangan','Sar-e Pol','Takhar','Uruzgan','Wardak','Zabul'],
    'Tajikistan': ['Dushanbe','Sughd','Khatlon','Gorno-Badakhshan','Districts of Republican Subordination'],
    'Tojikiston': ['Dushanbe','Sughd','Khatlon','Gorno-Badakhshan','Districts of Republican Subordination'],
    'Turkmenistan': ['Ahal','Balkan','Dashoguz','Lebap','Mary','Ashgabat'],
    'Kyrgyzstan': ['Batken','Chuy','Issyk-Kul','Jalal-Abad','Naryn','Osh','Talas','Bishkek'],
    'Kazakhstan': ['Almaty','Astana','Shymkent','Akmola','Aktobe','Almaty Region','Atyrau','East Kazakhstan','Jambyl','Karaganda','Kostanay','Kyzylorda','Mangystau','North Kazakhstan','Pavlodar','Turkistan','West Kazakhstan'],
    'China': ['Anhui','Beijing','Chongqing','Fujian','Gansu','Guangdong','Guangxi','Guizhou','Hainan','Hebei','Heilongjiang','Henan','Hubei','Hunan','Inner Mongolia','Jiangsu','Jiangxi','Jilin','Liaoning','Ningxia','Qinghai','Shaanxi','Shandong','Shanghai','Shanxi','Sichuan','Tianjin','Tibet','Xinjiang','Yunnan','Zhejiang'],
    'Russia': ['Moscow','Saint Petersburg','Krasnodar','Novosibirsk','Sverdlovsk','Tatarstan','Bashkortostan','Chelyabinsk','Samara','Rostov','Dagestan'],
    'Rossiya': ['Moscow','Saint Petersburg','Krasnodar','Novosibirsk','Sverdlovsk','Tatarstan','Bashkortostan','Chelyabinsk','Samara','Rostov','Dagestan'],
    'Turkey': ['Adana','Ankara','Antalya','Bursa','Denizli','Diyarbakir','Erzurum','Gaziantep','Istanbul','Izmir','Kayseri','Konya','Malatya','Mersin','Mugla','Samsun','Trabzon','Van'],
    'Iran': ['Alborz','Ardabil','Bushehr','Chaharmahal','East Azerbaijan','Esfahan','Fars','Gilan','Golestan','Hamadan','Hormozgan','Ilam','Kerman','Kermanshah','Khorasan','Khuzestan','Kurdistan','Lorestan','Markazi','Mazandaran','Qazvin','Qom','Semnan','Tehran','West Azerbaijan','Yazd','Zanjan'],
    'Iraq': ['Al Anbar','Babil','Baghdad','Basra','Dhi Qar','Diyala','Duhok','Erbil','Karbala','Kirkuk','Maysan','Muthanna','Najaf','Nineveh','Qadisiyyah','Saladin','Sulaymaniyah','Wasit'],
    'Nepal': ['Province No. 1','Madhesh','Bagmati','Gandaki','Lumbini','Karnali','Sudurpashchim'],
    'Sri Lanka': ['Central','Eastern','North Central','Northern','North Western','Sabaragamuwa','Southern','Uva','Western'],
    'Indonesia': ['Bali','Banten','Central Java','East Java','Jakarta','West Java','Yogyakarta','North Sumatra','South Sulawesi','West Kalimantan'],
    'Malaysia': ['Johor','Kedah','Kelantan','Kuala Lumpur','Melaka','Negeri Sembilan','Pahang','Penang','Perak','Perlis','Sabah','Sarawak','Selangor','Terengganu'],
    'Uzbekistan': ['Toshkent','Samarqand','Buxoro','Farg\'ona','Andijon','Namangan','Qashqadaryo','Surxondaryo','Xorazm','Navoiy','Jizzax','Sirdaryo','Qoraqalpog\'iston'],
    'Egypt': ['Alexandria','Aswan','Asyut','Cairo','Dakahlia','Damietta','Faiyum','Gharbia','Giza','Ismailia','Luxor','Minya','Port Said','Qalyubia','Sharqia','Sohag','Suez'],
    'Nigeria': ['Abia','Abuja','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno','Cross River','Delta','Ebonyi','Edo','Ekiti','Enugu','Gombe','Imo','Jigawa','Kaduna','Kano','Katsina','Kebbi','Kogi','Kwara','Lagos','Nasarawa','Niger','Ogun','Ondo','Osun','Oyo','Plateau','Rivers','Sokoto','Taraba','Yobe','Zamfara'],
    'Ghana': ['Ashanti','Brong-Ahafo','Central','Eastern','Greater Accra','Northern','Upper East','Upper West','Volta','Western'],
    'Kenya': ['Baringo','Bomet','Bungoma','Busia','Elgeyo-Marakwet','Embu','Garissa','Homa Bay','Isiolo','Kajiado','Kakamega','Kericho','Kiambu','Kilifi','Kirinyaga','Kisii','Kisumu','Kitui','Kwale','Laikipia','Lamu','Machakos','Makueni','Mandera','Marsabit','Meru','Migori','Mombasa','Nairobi','Nakuru','Nandi','Narok','Nyamira','Nyandarua','Nyeri','Samburu','Siaya','Taita-Taveta','Tana River','Tharaka-Nithi','Trans-Nzoia','Turkana','Uasin Gishu','Vihiga','Wajir','West Pokot'],
    'South Africa': ['Eastern Cape','Free State','Gauteng','KwaZulu-Natal','Limpopo','Mpumalanga','North West','Northern Cape','Western Cape'],
};

function searchSelect(config) {
    return {
        items: config.items || [],
        value: config.value || '',
        search: config.value || '',
        open: false,
        get filtered() {
            if (!this.search) return this.items;
            var q = this.search.toLowerCase();
            return this.items.filter(function(i) { return i.toLowerCase().includes(q); });
        },
        select(item) {
            this.value = item;
            this.search = item;
            this.open = false;
            // Davlat tanlanganda store'ga saqlash
            Alpine.store('birthCountry', item);
        }
    };
}

function regionSelect(config) {
    return {
        regions: [],
        value: config.value || '',
        search: config.value || '',
        open: false,
        init() {
            // Dastlabki davlat uchun viloyatlarni yuklash
            var country = Alpine.store('birthCountry');
            if (country) this.regions = countryRegions[country] || [];
        },
        get filtered() {
            if (this.regions.length === 0) return [];
            if (!this.search) return this.regions;
            var q = this.search.toLowerCase();
            return this.regions.filter(function(i) { return i.toLowerCase().includes(q); });
        },
        selectItem(item) {
            this.value = item;
            this.search = item;
            this.open = false;
        },
        updateRegions(country) {
            if (!country) { this.regions = []; return; }
            this.regions = countryRegions[country] || [];
        }
    };
}
</script>

{{-- Flatpickr --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof flatpickr === 'undefined') { console.error('Flatpickr not loaded!'); return; }
    var locale = '{{ app()->getLocale() }}';
    var months = locale === 'uz'
        ? ['Yanvar','Fevral','Mart','Aprel','May','Iyun','Iyul','Avgust','Sentabr','Oktabr','Noyabr','Dekabr']
        : ['January','February','March','April','May','June','July','August','September','October','November','December'];

    document.querySelectorAll('[data-datepicker]').forEach(function(el) {
        var origValue = el.value;
        var origName = el.name;

        // Hidden input serverga Y-m-d yuboradi
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = origName;
        hidden.value = origValue;
        el.parentNode.insertBefore(hidden, el.nextSibling);

        // Ko'rinadigan input — faqat chiroyli sana
        el.removeAttribute('name');
        el.style.cursor = 'pointer';
        el.style.backgroundColor = '#fff';

        // origValue = '2026-04-05' formatda
        var defDate = null;
        if (origValue && /^\d{4}-\d{2}-\d{2}$/.test(origValue)) {
            var p = origValue.split('-');
            defDate = new Date(parseInt(p[0]), parseInt(p[1])-1, parseInt(p[2]));
        }

        flatpickr(el, {
            dateFormat: 'd/m/Y',
            defaultDate: defDate,
            allowInput: true,
            clickOpens: true,
            parseDate: function(datestr) {
                // Y-m-d format (serverdan kelganda)
                if (/^\d{4}[\-\/]\d{1,2}[\-\/]\d{1,2}$/.test(datestr)) {
                    return new Date(datestr);
                }
                // d/m/Y yoki d.m.Y yoki d,m,Y format
                var parts = datestr.split(/[\/\.\,\-]/);
                if (parts.length === 3) {
                    var d = parseInt(parts[0]), m = parseInt(parts[1]) - 1, y = parseInt(parts[2]);
                    if (y < 100) y += 2000;
                    return new Date(y, m, d);
                }
                return new Date(datestr);
            },
            onChange: function(dates) {
                if (dates[0]) {
                    var d = dates[0];
                    hidden.value = d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
                }
            },
            onClose: function(dates, dateStr, instance) {
                if (dateStr && dates.length === 0) {
                    var parsed = instance.parseDate(dateStr);
                    if (parsed) { instance.setDate(parsed, true); }
                }
            }
        });
    });
});
</script>
</x-student-app-layout>
