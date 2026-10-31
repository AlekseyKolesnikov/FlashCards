<p class="learn-header muted">
    <a href="{{ route('learn.index') }}">Learn</a> ·
    {{ $session->fromLang->name }} → {{ $session->toLang->name }} · session {{ $session->seq }}
    @isset($cardsLeft)
        · <span class="cards-left">{{ $cardsLeft }} {{ $cardsLeft === 1 ? 'card' : 'cards' }} left</span>
    @endisset
</p>
