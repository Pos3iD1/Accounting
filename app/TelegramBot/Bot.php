<?php

namespace App\TelegramBot;

use App\Exceptions\AccountException;
use App\Exceptions\BotCommandException;
use App\Exceptions\ChatException;
use App\Exceptions\OperationException;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\OperationController;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class Bot
{
    /**
     * List of available bot commands with descriptions
     *
     * @var array<string>\n\t\t\t\t
     */
    private static array $availableBotCommands = [
        '/start' => 'Start the bot',
        '/help' => 'Show info about available commands',
        '/createaccount' => "Create new account with specified in new lines account name and description. Example:\n\t\t\t\t/createaccount\n\t\t\t\tAccount Name\n\t\t\t\tAccount Description",
        '/accountbalance' => "Show balance of account with specified in new line account name. Example: \n\t\t\t\t/accountbalance\n\t\t\t\tAccount Name",
        '/accountstatement' => "Show information about account with specified account name and statement period in days (statement period is optional, defaul value is 7 days). Example: \n\t\t\t\t/accountstatement\n\t\t\t\tAccount name\n\t\t\t\t31",
    ];

    /**
     * Handle every message sent to the bot
     *
     * @param Request $request
     * @return void
     */
    public function handle(Request $request): void
    {
        $message = $request['message'];

        if (array_key_exists('entities', $message)) {
            try {
                self::handleBotEntities($message);
            } catch (AccountException|ChatException|BotCommandException $exception) {
                self::sendMessage(['chat_id' => $message['chat']['id'], 'text' => $exception->getMessage()]);
            }
            return;
        }

        try {
            self::handleMessage($message);
        } catch (OperationException $exception) {
            self::sendMessage(['chat_id' => $message['chat']['id'], 'text' => $exception->getMessage()]);
        }

    }

    /**
     * Handle simple bot message. Now only allowed message is balance operations
     *
     * @param array $message
     * @return void
     * @throws OperationException
     */
    private static function handleMessage(array $message): void
    {
        if (!($message['text'][0] === '-' || $message['text'][0] === '+')) {
            self::sendMessage(['chat_id' => $message['chat']['id'], 'text' => 'Bad message structure']);
            return;
        }

        $splitMessageText = preg_split('/\n/', $message['text']);

        $size = (int)$splitMessageText[0];
        $accountName = $splitMessageText[1];
        $description = $splitMessageText[2];
        $author = $message['from']['id'];

        OperationController::store($accountName, $size, $description, $author);

        self::sendMessage(['chat_id' => $message['chat']['id'], 'text' => "Operation successfully saved\nAccount balance: " . Account::where('name', $accountName)->first()->balance]);
    }


    /**
     * Handle bot entities
     *
     * @param array $message
     * @return void
     * @throws AccountException|ChatException|BotCommandException
     */
    private static function handleBotEntities(array $message): void
    {
        foreach ($message['entities'] as $id => $entity) {
            if ($entity['type'] === 'bot_command') {
                self::handleBotCommand($message, $id);
            }
        }
    }


    /**
     * Handle and validate single bot command
     *
     * @param array $message
     * @param int $entityId
     * @return void
     * @throws AccountException|ChatException|BotCommandException
     */
    private static function handleBotCommand(array $message, int $entityId): void
    {
        $entity = $message['entities'][$entityId];

        $botCommand = substr($message['text'], $entity['offset'], $entity['length']);

        if ($entity['offset'] !== 0) {
            throw new BotCommandException('Bad command structure');
        }

        if (!array_key_exists($botCommand, self::$availableBotCommands)) {
            throw new BotCommandException('Unknown bot command: ' . $botCommand);
        }

        switch ($botCommand) {
            case '/start':
            {
                ChatController::store($message['chat']['id']);

                self::sendMessage(['chat_id' => $message['chat']['id'], 'text' => 'Welcome to Accounting Manager Bot!...']);

                break;
            }
            case '/help':
            {
                $helpMessage = '';
                foreach (self::$availableBotCommands as $botCommand => $description) {
                    $helpMessage .= $botCommand . ' - ' . $description . "\n\n";
                }

                self::sendMessage(['chat_id' => $message['chat']['id'], 'text' => $helpMessage]);
                break;
            }
            case '/createaccount':
            {
                $splitMessageText = preg_split('/\n/', $message['text']);

                if (count($splitMessageText) < 2) {
                    throw new BotCommandException('Bad command structure');
                }

                AccountController::store($message['chat']['id'], $splitMessageText[1], array_key_exists(2, $splitMessageText) ? $splitMessageText[2] : null);

                self::sendMessage(['chat_id' => $message['chat']['id'], 'text' => 'Account successfully created']);

                break;
            }
            case '/accountbalance':
            {
                $splitMessageText = preg_split('/\n/', $message['text']);

                if (count($splitMessageText) < 2) {
                    throw new BotCommandException('Bad command structure');
                }

                if (!Account::where('name', $splitMessageText[1])->exists()) {
                    throw new BotCommandException('Can not find account with given name: ' . $splitMessageText[1]);
                }

                $account = Account::where('name', $splitMessageText[1])->first();

                self::sendMessage(['chat_id' => $message['chat']['id'], 'text' => 'Account balance: ' . $account->balance]);
                break;
            }
            case '/accountstatement':
            {
                $splitMessageText = preg_split('/\n/', $message['text']);

                if (count($splitMessageText) < 2) {
                    throw new BotCommandException('Bad command structure');
                }

                if (!Account::where('name', $splitMessageText[1])->exists()) {
                    throw new BotCommandException('Can not find account with given name: ' . $splitMessageText[1]);
                }

                $account = Account::where('name', $splitMessageText[1])->first();

                $accountStatementPeriod = array_key_exists(2, $splitMessageText) ? -1 * ((int)$splitMessageText) : -7;

                $infoMessage = '';
                foreach ($account->operations as $operation) {
                    if (!($operation->created_at > now()->addDays($accountStatementPeriod))) {
                        continue;
                    }

                    $infoMessage .= $operation->created_at->format('d.m H:i') . ': ' . $operation->size . ' - ' . $operation->description . "\n";
                }

                $infoMessage .= 'Account balance: ' . $account->balance;

                self::sendMessage(['chat_id' => $message['chat']['id'], 'text' => $infoMessage]);
                break;
            }
        }
    }


    /**
     * Send message to chat from witch got a user message
     *
     * @param array $message
     * @return void
     */
    private static function sendMessage(array $message): void
    {
        Http::post('https://api.telegram.org/bot' . env('TELEGRAM_TOKEN') . '/sendMessage', $message);
    }
}
