<?php

$user = new App\Models\User();

$user->where('email', '')->orWhere('name', '