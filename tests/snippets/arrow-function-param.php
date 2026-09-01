<?php

App\Models\User::where(fn (\Illuminate\Database\Query\Builder $q) => $q->whereIn('