<?php

App\Models\User::with(['team' => fn (\Illuminate\Database\Query\Builder $q) => $q->where('