<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Xodimni baholash — LMS</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            padding: 32px 28px;
            max-width: 420px;
            width: 100%;
        }
        .header { text-align: center; margin-bottom: 28px; }
        .avatar {
            width: 72px; height: 72px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 14px;
            color: #fff; font-size: 28px; font-weight: 700;
        }
        .staff-name { font-size: 20px; font-weight: 700; color: #1a1a2e; }
        .subtitle { font-size: 14px; color: #888; margin-top: 4px; }

        .stars { display: flex; justify-content: center; gap: 8px; margin-bottom: 24px; }
        .star-btn {
            background: none; border: none; cursor: pointer;
            font-size: 44px; color: #ddd; transition: color 0.15s, transform 0.15s;
            padding: 0; line-height: 1;
        }
        .star-btn.active { color: #fbbf24; }
        .star-btn:hover { transform: scale(1.15); }

        .rating-label {
            text-align: center; font-size: 14px; color: #666;
            margin-bottom: 20px; min-height: 20px;
        }

        textarea {
            width: 100%; border: 2px solid #e5e7eb; border-radius: 12px;
            padding: 14px; font-size: 15px; resize: vertical; min-height: 90px;
            font-family: inherit; transition: border-color 0.2s;
        }
        textarea:focus { outline: none; border-color: #667eea; }
        textarea::placeholder { color: #bbb; }

        .submit-btn {
            width: 100%; padding: 14px; border: none; border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff; font-size: 16px; font-weight: 600;
            cursor: pointer; margin-top: 18px; transition: opacity 0.2s;
        }
        .submit-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .submit-btn:hover:not(:disabled) { opacity: 0.9; }

        .alert {
            padding: 14px 18px; border-radius: 12px; margin-bottom: 20px;
            font-size: 14px; text-align: center;
        }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }

        .error-text { color: #ef4444; font-size: 13px; margin-top: 4px; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        @if(session('success'))
            <div class="alert alert-success">
                &#10003; {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="header">
            <div class="avatar">{{ mb_substr($teacher->full_name, 0, 1) }}</div>
            <div class="staff-name">{{ $teacher->full_name }}</div>
            <div class="subtitle">Xizmat sifatini baholang</div>
        </div>

        @if(!session('success'))
        <form method="POST" action="{{ route('staff-evaluate.submit', $token) }}" id="evalForm">
            @csrf
            <input type="hidden" name="rating" id="ratingInput" value="">

            <div class="stars" id="starsContainer">
                @for($i = 1; $i <= 5; $i++)
                <button type="button" class="star-btn" data-value="{{ $i }}">&#9733;</button>
                @endfor
            </div>

            <div class="rating-label" id="ratingLabel"></div>

            @error('rating')
                <div class="error-text" style="margin-top: -16px; margin-bottom: 16px;">{{ $message }}</div>
            @enderror

            <textarea name="comment" placeholder="Izoh yozing (ixtiyoriy)..." maxlength="1000">{{ old('comment') }}</textarea>

            <button type="submit" class="submit-btn" id="submitBtn" disabled>Baholash</button>
        </form>
        @endif
    </div>

    <script>
        const labels = {
            1: 'Juda yomon',
            2: 'Yomon',
            3: 'O\'rtacha',
            4: 'Yaxshi',
            5: 'A\'lo'
        };

        const stars = document.querySelectorAll('.star-btn');
        const ratingInput = document.getElementById('ratingInput');
        const ratingLabel = document.getElementById('ratingLabel');
        const submitBtn = document.getElementById('submitBtn');
        let selected = 0;

        stars.forEach(btn => {
            btn.addEventListener('click', function() {
                selected = parseInt(this.dataset.value);
                ratingInput.value = selected;
                submitBtn.disabled = false;
                updateStars();
            });

            btn.addEventListener('mouseenter', function() {
                highlightStars(parseInt(this.dataset.value));
            });

            btn.addEventListener('mouseleave', function() {
                highlightStars(selected);
            });
        });

        function highlightStars(value) {
            stars.forEach(s => {
                const v = parseInt(s.dataset.value);
                s.classList.toggle('active', v <= value);
            });
            ratingLabel.textContent = value > 0 ? labels[value] : '';
        }

        function updateStars() {
            highlightStars(selected);
        }
    </script>
</body>
</html>
