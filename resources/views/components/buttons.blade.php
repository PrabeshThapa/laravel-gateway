@once
    <style>
        .npg-payment-choice {
            font-family: sans-serif;
        }

        .npg-payment-choice__cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 230px), 1fr));
            gap: 12px;
        }

        .npg-payment-choice__card {
            position: relative;
            display: grid;
            min-height: 210px;
            grid-template-rows: 80px 54px minmax(48px, 1fr);
            gap: 12px;
            justify-items: center;
            align-content: center;
            padding: 24px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            color: #172554;
            cursor: pointer;
            text-align: center;
            transition: border-color 150ms ease, background-color 150ms ease;
        }

        .npg-payment-choice__card:has(input:checked) {
            border-color: #2563eb;
            background: #eff6ff;
            box-shadow: 0 0 0 1px #2563eb;
        }

        .npg-payment-choice__radio {
            position: absolute;
            top: 16px;
            left: 20px;
            width: 18px;
            height: 18px;
            margin: 0;
            accent-color: #0875e1;
        }

        .npg-payment-choice__logo {
            display: flex;
            width: 230px;
            height: 80px;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .npg-payment-choice__logo img {
            display: block;
            width: 200px;
            height: 80px;
            object-fit: contain;
        }

        .npg-payment-choice__name {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            line-height: 1.4;
            text-align: center;
        }

        .npg-payment-choice__description {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            color: #64748b;
            font-size: 15px;
            line-height: 1.5;
            text-align: center;
        }

        .npg-payment-choice__actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .npg-payment-choice__submit {
            display: inline-flex;
            min-height: 52px;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 12px 24px;
            border: 0;
            border-radius: 9px;
            background: #0866e8;
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-size: 16px;
            font-weight: 700;
        }

        .npg-payment-choice__submit:hover {
            background: #0758c9;
        }

        .npg-payment-choice__submit:focus-visible,
        .npg-payment-choice__radio:focus-visible {
            outline: 3px solid #93c5fd;
            outline-offset: 3px;
        }

        @media (max-width: 520px) {
            .npg-payment-choice__card {
                min-height: 180px;
            }

            .npg-payment-choice__actions {
                justify-content: stretch;
            }

            .npg-payment-choice__submit {
                width: 100%;
            }
        }
    </style>

    <script>
        document.addEventListener('change', function (event) {
            const radio = event.target;
            if (radio.matches('.npg-payment-choice__radio')) {
                radio.closest('.npg-payment-choice__form').action = radio.dataset.url;
            }
        });
    </script>
@endonce

<div class="npg-payment-choice">
    @forelse ($enabledGateways as $gateway)
        @php
            $url = route('nepal-payment.initiate', ['gateway' => $gateway['key']]);
            $name = match ($gateway['key']) {
                'esewa' => 'eSewa',
                'fonepay' => 'Fonepay',
                'khalti' => 'Khalti',
                'nepal_payment' => 'Nepal Payment Solution',
                default => $gateway['label'],
            };
            $logoUrl = match ($gateway['key']) {
                'esewa', 'fonepay', 'khalti', 'nepal_payment' => route('nepal-payment.asset', ['logo' => $gateway['key']]),
                default => null,
            };
            $description = match ($gateway['key']) {
                'esewa' => 'Pay securely through eSewa.',
                'fonepay' => 'Pay through Fonepay mobile banking.',
                'khalti' => 'Pay securely through Khalti.',
                'nepal_payment' => 'Pay through Nepal Payment Solution.',
                default => 'Continue securely with this payment method.',
            };
        @endphp
        @if ($loop->first)
            <form class="npg-payment-choice__form" method="get" action="{{ $url }}">
                <div class="npg-payment-choice__cards">
        @endif
                    <label class="npg-payment-choice__card">
                        <input
                            class="npg-payment-choice__radio"
                            type="radio"
                            name="gateway"
                            value="{{ $gateway['key'] }}"
                            data-url="{{ $url }}"
                            @checked($loop->first)
                            required
                        >
                        <span class="npg-payment-choice__logo npg-payment-choice__logo--{{ $gateway['key'] }}" aria-hidden="true">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="">
                            @else
                                {{ $name }}
                            @endif
                        </span>
                        <span class="npg-payment-choice__name">{{ $name }}</span>
                        <span class="npg-payment-choice__description">{{ $description }}</span>
                    </label>
        @if ($loop->last)
                </div>
                <input type="hidden" name="amount" value="{{ $amount }}">
                @if ($successUrl)
                    <input type="hidden" name="success_url" value="{{ $successUrl }}">
                @endif
                @if ($failureUrl)
                    <input type="hidden" name="failure_url" value="{{ $failureUrl }}">
                @endif
                @if ($callbackUrl)
                    <input type="hidden" name="callback_url" value="{{ $callbackUrl }}">
                @endif
                <div class="npg-payment-choice__actions">
                    <button class="npg-payment-choice__submit" type="submit">
                        {{ $btnName }} <span aria-hidden="true">›</span>
                    </button>
                </div>
            </form>
        @endif
    @empty
        <p style="color:#94a3b8;font-family:sans-serif;font-size:14px;">No payment methods are currently available.</p>
    @endforelse
</div>
