<?php

namespace App\DTO;

class UserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password = null,
        public ?string $role = null,
        public ?int $id = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'] ?? null,
            role: $data['role'] ?? null,
            id: $data['id'] ?? null
        );
    }

    public function toArray(): array
    {
        $array = [
            'name' => $this->name,
            'email' => $this->email,
        ];

        if ($this->password) {
            $array['password'] = $this->password;
        }

        if ($this->role) {
            $array['role'] = $this->role;
        }

        if ($this->id) {
            $array['id'] = $this->id;
        }

        return $array;
    }
}
