<label for="username">Username</label>
<input id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username"
       minlength="3" maxlength="32">
<p class="hint">3–32 characters: letters, digits, dashes and underscores.</p>
@error('username') <p class="error">{{ $message }}</p> @enderror

<label for="password">Password</label>
<input id="password" name="password" type="password" required autocomplete="new-password" minlength="8">
<p class="hint">At least 8 characters.</p>
@error('password') <p class="error">{{ $message }}</p> @enderror

<label for="password_confirmation">Repeat password</label>
<input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
