<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Creates resellers, sub-resellers and their customers in one step: login + account, ready to use. */
class Provisioning
{
    /** Validation rules for plan costs set by a parent (platform owner when $parent is null). */
    public static function costRules(?Reseller $parent): array
    {
        $rules = ['costs' => ['required', 'array']];
        foreach (config('billing.plans') as $key => $plan) {
            $min = $parent ? $parent->minChildCost($key) : 0;
            $rules["costs.$key"] = ['required', 'numeric', 'min:'.$min, 'max:1000000'];
        }

        return $rules;
    }

    public static function costMessages(?Reseller $parent): array
    {
        $m = [];
        foreach (config('billing.plans') as $key => $plan) {
            $m["costs.$key.min"] = "{$plan['name']} cost cannot be below your own cost (₹".($parent?->minChildCost($key) ?? 0).').';
        }

        return $m;
    }

    public static function slugRules(?Reseller $ignore = null): array
    {
        return ['required', 'alpha_dash:ascii', 'min:3', 'max:40', 'lowercase', Rule::notIn(['www', 'app', 'admin', 'api', 'mail']), Rule::unique('resellers', 'slug')->ignore($ignore)];
    }

    /** @return array{0: Reseller, 1: string} the reseller and the login password to share */
    public static function createReseller(?Reseller $parent, array $data): array
    {
        $password = $data['password'] ?? Str::password(12, symbols: false);

        $reseller = DB::transaction(function () use ($parent, $data, $password) {
            $user = User::create(['name' => $data['contact_name'], 'email' => $data['email'], 'password' => $password]);
            $reseller = Reseller::create([
                'parent_id' => $parent?->id, 'user_id' => $user->id, 'name' => $data['name'], 'slug' => $data['slug'],
                'color' => $parent?->color ?? '#0B4E99', 'support_email' => $data['email'], 'support_phone' => $data['phone'] ?? null,
                'costs' => array_map('floatval', $data['costs']),
                'prices' => array_map(fn ($c) => (float) ceil($c / 0.6), $data['costs']), // starting retail price, the reseller can change it
                'trial_days' => min((int) ($data['trial_days'] ?? 7), config('reseller.max_trial_days')),
                'can_create_resellers' => ! empty($data['can_create_resellers']),
            ]);
            $user->forceFill(['reseller_id' => $reseller->id])->save();

            return $reseller;
        });

        return [$reseller, $password];
    }

    /**
     * New customer under a reseller: owner login + workspace on trial, optionally on a paid plan right away.
     *
     * @return array{0: Workspace, 1: string}
     */
    public static function createCustomer(Reseller $reseller, array $data, ?User $by = null): array
    {
        $password = $data['password'] ?? Str::password(12, symbols: false);

        $workspace = DB::transaction(function () use ($reseller, $data, $password, $by) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $password]);
            $workspace = $user->createWorkspace($data['company']);
            $workspace->forceFill([
                'reseller_id' => $reseller->id, 'trial_ends_at' => now()->addDays($reseller->trial_days),
                'billing_phone' => $data['phone'] ?? null, 'billing_email' => $data['email'],
            ])->save();
            if (! empty($data['plan'])) {
                Wallet::activate($reseller, $workspace, $data['plan'], $data['cycle'] ?? 'monthly', $by);
            }

            return $workspace;
        });

        return [$workspace, $password];
    }

    /** Throws when the reseller would sell below its own cost. */
    public static function checkPrices(Reseller $reseller, array $prices): void
    {
        foreach ($prices as $plan => $price) {
            if ((float) $price < $reseller->cost($plan)) {
                throw ValidationException::withMessages(["prices.$plan" => config("billing.plans.$plan.name").' price is below your cost.']);
            }
        }
    }
}
