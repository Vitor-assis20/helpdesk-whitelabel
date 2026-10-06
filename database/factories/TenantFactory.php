<?php

namespace Database\Factories;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'timezone' => 'America/Sao_Paulo',
            'branding' => ['primary_color' => fake()->hexColor(), 'logo_path' => null],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * Vincula um usuário à equipe com o papel informado.
     */
    public function withUser(User $user, TenantRole $role = TenantRole::Owner): static
    {
        return $this->afterCreating(fn (Tenant $tenant) => $tenant->users()->attach($user, ['role' => $role->value]));
    }
}
