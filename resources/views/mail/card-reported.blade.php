{!! $reporter->username !!} reported a mistake in a card while learning {!! $fromLang->name !!} → {!! $toLang->name !!}.

Card: {!! $cardUrl !!}

{!! $fromLang->name !!}: {!! $card->sideField('word', $fromLang->id) !!}
{!! $toLang->name !!}: {!! $card->sideField('word', $toLang->id) !!}
@if ($correction !== null)

Suggested translation: {!! $correction !!}
@endif
@if ($comment !== null)

Comment:
{!! $comment !!}
@endif
