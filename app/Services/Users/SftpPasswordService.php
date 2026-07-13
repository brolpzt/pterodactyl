<?php

namespace Pterodactyl\Services\Users;

use Pterodactyl\Models\User;
use Pterodactyl\Helpers\Utilities;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Encryption\DecryptException;

class SftpPasswordService
{
    public function __construct(private Encrypter $encrypter)
    {
    }

    public function hasPassword(User $user): bool
    {
        return !empty($user->sftp_password);
    }

    /**
     * @throws DecryptException
     */
    public function reveal(User $user): string
    {
        if (empty($user->sftp_password)) {
            throw new DecryptException('No SFTP password is configured for this account.');
        }

        return $this->encrypter->decrypt($user->sftp_password);
    }

    /**
     * Generate a new SFTP password for the user and return the plaintext value.
     */
    public function rotate(User $user): string
    {
        $password = Utilities::randomStringWithSpecialCharacters(24);

        $user->forceFill([
            'sftp_password' => $this->encrypter->encrypt($password),
        ])->save();

        return $password;
    }
}
