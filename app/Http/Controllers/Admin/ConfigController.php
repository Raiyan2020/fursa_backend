<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Config;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConfigController extends Controller
{
    public function edit()
    {
        $config = Config::query()->first() ?? Config::create([]);

        return view('dashboard.settings.edit', compact('config'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'cycle_type' => ['nullable', Rule::in(['monthly', 'quarterly', 'semi_annual', 'annual'])],
            'cycle_scope' => ['nullable', Rule::in(['current', 'previous', 'custom'])],
            'cycle_year' => ['nullable', 'integer'],
            'cycle_index' => ['nullable', 'integer'],
            'number_of_opportunities' => ['nullable', 'integer'],
            'time_duration' => ['nullable', 'integer'],
            'time_unit' => ['nullable', Rule::in(['days', 'weeks', 'months', 'years'])],
            'manual_attendance_threshold' => ['nullable', 'integer'],
        ]);

        $config = Config::query()->first();
        $config->update($request->only([
            'cycle_type',
            'cycle_scope',
            'cycle_year',
            'cycle_index',
            'number_of_opportunities',
            'time_duration',
            'time_unit',
            'manual_attendance_threshold',
        ]));
        updated();

        return back();
    }
}
