<?php

namespace App\Http\Controllers;

use App\Exceptions\OperationException;
use App\Models\Account;
use App\Models\Operation;

class OperationController extends Controller
{
    /**
     * Store operation into database
     *
     * @param string $accountName
     * @param int $size
     * @param string $description
     * @param string $author
     * @return bool
     * @throws OperationException
     */
    public static function store(string $accountName, int $size, string $description, string $author): bool
    {
        if (!Account::where('name', $accountName)->exists()) {
            throw new OperationException('Can not find account with given name: ' . $accountName);
        }

        $operation = new Operation();

        $account = Account::where('name', $accountName)->first();

        $operation->account_id = $account->id;
        $operation->size = $size;
        $operation->description = $description;
        $operation->author = $author;
        $operation->save();

        $account->balance += $size;
        $account->save();

        return true;
    }
}
