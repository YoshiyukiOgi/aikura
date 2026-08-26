<?php

namespace App\Providers;

use App\Models\Retail\RetailSystemSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('retail.*', function ($view): void {
            $systemName = '小売販売システム';
            $themes = $this->retailThemes();
            $themeName = 'blue';
            $theme = $themes[$themeName];

            try {
                if (Schema::connection(config('retail.database.connection', 'retail'))->hasTable('retail_system_settings')) {
                    $setting = RetailSystemSetting::query()->first();
                    $systemName = $setting?->system_name ?: $systemName;
                    $themeName = $setting?->theme ?: $themeName;
                    $theme = $themes[$themeName] ?? $themes['blue'];
                }
            } catch (Throwable) {
                // Keep rendering usable during initial migrations or when the retail DB is unavailable.
            }

            $view->with([
                'retailSystemName' => $systemName,
                'retailTheme' => $theme,
                'retailThemeName' => $themeName,
                'retailThemeLabels' => $this->retailThemeLabels(),
            ]);
        });
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function retailThemes(): array
    {
        return [
            'blue' => ['primary' => '#126deb', 'primaryDark' => '#075ecf', 'sidebar' => '#0d1f36', 'bg' => '#f5f7fb', 'card' => '#ffffff', 'line' => '#dce4ee'],
            'green' => ['primary' => '#15803d', 'primaryDark' => '#166534', 'sidebar' => '#0f2f24', 'bg' => '#f5fbf7', 'card' => '#ffffff', 'line' => '#d7e7dc'],
            'brown' => ['primary' => '#b45309', 'primaryDark' => '#92400e', 'sidebar' => '#332313', 'bg' => '#fbf7f1', 'card' => '#ffffff', 'line' => '#eadfce'],
            'indigo' => ['primary' => '#4f46e5', 'primaryDark' => '#4338ca', 'sidebar' => '#1e1b4b', 'bg' => '#f6f7ff', 'card' => '#ffffff', 'line' => '#dde1f5'],
            'slate' => ['primary' => '#475569', 'primaryDark' => '#334155', 'sidebar' => '#111827', 'bg' => '#f8fafc', 'card' => '#ffffff', 'line' => '#d8dee8'],
            'neon' => ['primary' => '#00c8ff', 'primaryDark' => '#007da3', 'sidebar' => '#10081f', 'bg' => '#f6fbff', 'card' => '#ffffff', 'line' => '#bcecff'],
            'disco' => ['primary' => '#ff2bd6', 'primaryDark' => '#9b1dff', 'sidebar' => '#15001f', 'bg' => '#fff7fe', 'card' => '#ffffff', 'line' => '#ffd0f5'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function retailThemeLabels(): array
    {
        return [
            'blue' => 'ブルー',
            'green' => 'グリーン',
            'brown' => 'ブラウン',
            'indigo' => 'インディゴ',
            'slate' => 'スレート',
            'neon' => 'ネオン',
            'disco' => 'ディスコ',
        ];
    }
}

