import './bootstrap';

// Alpine.js Livewire v3 orqali yuklanadi (@livewireScripts),
// shuning uchun bu yerda alohida import qilish SHART EMAS.
// Aks holda "Detected multiple instances of Alpine running" xatosi chiqadi.

import 'flowbite';

// Flatpickr — global date/range picker
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import { UzbekLatin } from 'flatpickr/dist/l10n/uz_latn.js';
flatpickr.localize(UzbekLatin);
window.flatpickr = flatpickr;
