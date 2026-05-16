@php
    // $group — har karta uchun noyob (masalan 'ageUmumiy'); JS shu nomdan
    // foydalanib qaysi chartni qayta render qilishni biladi.
    $group = $group ?? 'edu';
@endphp
<div class="edu-tabs" data-edu-tabs="{{ $group }}">
    <button type="button" class="edu-tab-btn active" data-edu="all">Hammasi</button>
    <button type="button" class="edu-tab-btn" data-edu="bakalavr">Bakalavr</button>
    <button type="button" class="edu-tab-btn" data-edu="magistr">Magistratura</button>
    <button type="button" class="edu-tab-btn" data-edu="ordinatura">Ordinatura</button>
</div>
