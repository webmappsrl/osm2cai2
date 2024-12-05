@if (count($users) > 0)
    <ol style="margin-top:10px;">
        @foreach ($users as $user)
            <li>
                <a style="text-decoration:none; color: darkgreen;" href="{{ url('/resources/users/' . $user->user_id) }}">
                    {{ $user->user_name }}
                </a>
                | N. Validazioni: <strong>{{ $user->numero_validazioni }}</strong>
            </li>
        @endforeach
    </ol>
@else
    <p style="margin-top:10px; text-align:center; color:#666;">
        Nessun utente ha ancora effettuato validazioni
    </p>
@endif
