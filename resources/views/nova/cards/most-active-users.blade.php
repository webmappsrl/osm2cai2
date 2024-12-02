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
