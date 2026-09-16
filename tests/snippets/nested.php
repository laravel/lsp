<?php

Route::get('/', function () {
    trans('auth.throttle');
    App\Models\User::where('