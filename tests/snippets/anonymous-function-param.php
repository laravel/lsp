<?php

App\Models\User::where(function (\Illuminate\Database\Query\Builder $q) {
    $q->whereIn('