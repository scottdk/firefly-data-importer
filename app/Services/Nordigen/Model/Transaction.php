<?php
/*
 * Transaction.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\Nordigen\Model;

use App\Rules\Iban;
use Carbon\Carbon;
use DateTimeInterface;
use JsonException;
use Ramsey\Uuid\Uuid;
use Validator;

/**
 * Class Transaction
 */
class Transaction
{
    public string  $accountIdentifier;
    public string  $additionalInformation;
    public string  $additionalInformationStructured;
    public Balance $balanceAfterTransaction;
    public string  $bankTransactionCode;
    public ?Carbon $bookingDate;
    public string  $checkId;
    public string  $creditorAccountBban;
    public string  $creditorAccountCurrency;
    public string  $creditorAccountIban; // is an array (see https://github.com/firefly-iii/firefly-iii/issues/5286)
    // TODO use currency exchange info in notes
    public string $creditorAgent;
    public string $creditorId;
    public string $creditorName;
    public string $currencyCode;
    public array  $currencyExchange;
    public string $debtorAccountBban;
    public string $debtorAccountCurrency;
    public string $debtorAccountIban;
    public string $debtorAgent;
    public string $debtorName;
    public string $endToEndId;
    public string $entryReference;
    public string $key;
    public string $mandateId;
    public string $proprietaryBank;

    // debtorAccount is an array, but is saved as strings
    // iban, currency
    public string $purposeCode;
    public string $remittanceInformationStructured;
    public array  $remittanceInformationStructuredArray;

    // creditorAccount is an array, but saved as strings:
    // iban, currency
    public string $remittanceInformationUnstructured;
    public array  $remittanceInformationUnstructuredArray;
    public string $transactionAmount;

    // transactionAmount is an array, but is saved as strings
    // amount, currency
    public string $transactionId;
    public string $ultimateCreditor;

    // my own custom fields
    public string $ultimateDebtor;

    // undocumented fields
    public ?Carbon $valueDate;

    /**
     * Creates a transaction from a downloaded array.
     *
     * @param $array
     *
     * @return self
     */
    public static function fromArray($array): self
    {
        app('log')->debug('Nordigen transaction from array', $array);
        $object = new self();

        $object->additionalInformation                  = trim($array['additionalInformation'] ?? '');
        $object->additionalInformationStructured        = trim($array['additionalInformationStructured'] ?? '');
        $object->bankTransactionCode                    = trim($array['bankTransactionCode'] ?? '');
        $object->bookingDate                            = array_key_exists('bookingDate', $array) ? Carbon::createFromFormat(
            '!Y-m-d',
            $array['bookingDate'],
            config('app.timezone')
        ) : null;
        $object->key                                    = trim($array['key'] ?? '');
        $object->checkId                                = trim($array['checkId'] ?? '');
        $object->creditorAgent                          = trim($array['creditorAgent'] ?? '');
        $object->creditorId                             = trim($array['creditorId'] ?? '');
        $object->creditorName                           = trim($array['creditorName'] ?? '');
        $object->currencyExchange                       = $array['currencyExchange'] ?? [];
        $object->debtorAgent                            = trim($array['debtorAgent'] ?? '');
        $object->debtorName                             = trim($array['debtorName'] ?? '');
        $object->entryReference                         = trim($array['entryReference'] ?? '');
        $object->mandateId                              = trim($array['mandateId'] ?? '');
        $object->proprietaryBank                        = trim($array['proprietaryBank'] ?? '');
        $object->purposeCode                            = trim($array['purposeCode'] ?? '');
        $object->remittanceInformationStructured        = trim($array['remittanceInformationStructured'] ?? '');
        $object->remittanceInformationStructuredArray   = $array['remittanceInformationStructuredArray'] ?? [];
        $object->remittanceInformationUnstructured      = trim($array['remittanceInformationUnstructured'] ?? '');
        $object->remittanceInformationUnstructuredArray = $array['remittanceInformationUnstructuredArray'] ?? [];
        $object->transactionId                          = trim($array['transactionId'] ?? '');
        $object->ultimateCreditor                       = trim($array['ultimateCreditor'] ?? '');
        $object->ultimateDebtor                         = trim($array['ultimateDebtor'] ?? '');
        $object->valueDate                              = array_key_exists('valueDate', $array) ? Carbon::createFromFormat(
            '!Y-m-d',
            $array['valueDate'],
            config('app.timezone')
        ) : null;

        // undocumented values
        $object->endToEndId = trim($array['endToEndId'] ?? ''); // from Rabobank NL

        // overrule transaction id when empty using the internal ID:
        if ('' === $object->transactionId) {
            $object->transactionId = trim($array['internalTransactionId'] ?? '');
        }

        // models:
        if (array_key_exists('balanceAfterTransaction', $array) && is_array($array['balanceAfterTransaction'])) {
            $object->balanceAfterTransaction = Balance::createFromArray($array['balanceAfterTransaction'] ?? []);
        }
        if (array_key_exists('balanceAfterTransaction', $array) && !is_array($array['balanceAfterTransaction'])) {
            app('log')->warning(sprintf('balanceAfterTransaction is not an array: %s', $array['balanceAfterTransaction']));
            $object->balanceAfterTransaction = Balance::createFromArray([]);
        }
        if (!array_key_exists('balanceAfterTransaction', $array)) {
            $object->balanceAfterTransaction = Balance::createFromArray([]);
        }


        // array values:
        $object->creditorAccountIban     = trim($array['creditorAccount']['iban'] ?? '');
        $object->creditorAccountBban     = trim($array['creditorAccount']['bban'] ?? '');
        $object->creditorAccountCurrency = trim($array['creditorAccount']['currency'] ?? '');

        $object->debtorAccountIban     = trim($array['debtorAccount']['iban'] ?? '');
        $object->debtorAccountBban     = trim($array['debtorAccount']['bban'] ?? '');
        $object->debtorAccountCurrency = trim($array['debtorAccount']['currency'] ?? '');

        $object->transactionAmount = trim($array['transactionAmount']['amount'] ?? '');
        $object->currencyCode      = trim($array['transactionAmount']['currency'] ?? '');

        // other fields:
        $object->accountIdentifier = '';

        // generate transactionID if empty:
        if ('' === $object->transactionId) {
            $hash = hash('sha256', (string)microtime());
            try {
                $hash = hash('sha256', json_encode($array, JSON_THROW_ON_ERROR));
                app('log')->warning('Generated random transaction ID from array!');
            } catch (JsonException $e) {
                app('log')->error(sprintf('Could not parse array into JSON: %s', $e->getMessage()));
            }
            $object->transactionId = (string)Uuid::uuid5(config('importer.namespace'), $hash);
        }
        app('log')->debug(sprintf('Downloaded transaction with ID "%s"', $object->transactionId));

        return $object;
    }

    /**
     * @param array $array
     *
     * @return static
     */
    public static function fromLocalArray(array $array): self
    {
        $object = new self();

        $object->additionalInformation                  = $array['additional_information'];
        $object->additionalInformationStructured        = $array['additional_information_structured'];
        $object->balanceAfterTransaction                = Balance::fromLocalArray($array['balance_after_transaction']);
        $object->bankTransactionCode                    = $array['bank_transaction_code'];
        $object->bookingDate                            = Carbon::createFromFormat(DateTimeInterface::W3C, $array['booking_date']);
        $object->checkId                                = $array['check_id'];
        $object->creditorAgent                          = $array['creditor_agent'];
        $object->creditorId                             = $array['creditor_id'];
        $object->creditorName                           = $array['creditor_name'];
        $object->currencyExchange                       = $array['currency_exchange'];
        $object->debtorAgent                            = $array['debtor_agent'];
        $object->debtorName                             = $array['debtor_name'];
        $object->entryReference                         = $array['entry_reference'];
        $object->key                                    = $array['key'];
        $object->mandateId                              = $array['mandate_id'];
        $object->proprietaryBank                        = $array['proprietary_bank'];
        $object->purposeCode                            = $array['purpose_code'];
        $object->remittanceInformationStructured        = $array['remittance_information_structured'];
        $object->remittanceInformationStructuredArray   = $array['remittance_information_structured_array'];
        $object->remittanceInformationUnstructured      = $array['remittance_information_unstructured'];
        $object->remittanceInformationUnstructuredArray = $array['remittance_information_unstructured_array'];
        $object->transactionId                          = $array['transaction_id'];
        $object->ultimateCreditor                       = $array['ultimate_creditor'];
        $object->ultimateDebtor                         = $array['ultimate_debtor'];
        $object->valueDate                              = Carbon::createFromFormat(DateTimeInterface::W3C, $array['value_date']);
        $object->transactionAmount                      = $array['transaction_amount']['amount'];
        $object->currencyCode                           = $array['transaction_amount']['currency'];
        $object->accountIdentifier                      = $array['account_identifier'];

        // undocumented values:
        $object->endToEndId = $array['end_to_end_id'];

        // TODO copy paste code.
        $object->debtorAccountIban   = array_key_exists('iban', $array['debtor_account']) ? $array['debtor_account']['iban'] : '';
        $object->creditorAccountIban = array_key_exists('iban', $array['creditor_account']) ? $array['creditor_account']['iban'] : '';

        $object->debtorAccountBban   = array_key_exists('bban', $array['debtor_account']) ? $array['debtor_account']['bban'] : '';
        $object->creditorAccountBban = array_key_exists('bban', $array['creditor_account']) ? $array['creditor_account']['bban'] : '';

        $object->debtorAccountCurrency   = array_key_exists('currency', $array['debtor_account']) ? $array['debtor_account']['currency'] : '';
        $object->creditorAccountCurrency = array_key_exists('currency', $array['creditor_account']) ? $array['creditor_account']['currency'] : '';

        //$object-> = $array[''];

        // generate transactionID if empty:
        if ('' === $object->transactionId) {
            $hash = hash('sha256', (string)microtime());
            try {
                $hash = hash('sha256', json_encode($array, JSON_THROW_ON_ERROR));
            } catch (JsonException $e) {
                app('log')->error(sprintf('Could not parse array into JSON: %s', $e->getMessage()));
            }
            $object->transactionId = (string)Uuid::uuid5(config('importer.namespace'), $hash);
        }

        return $object;
    }

    /**
     * @return Carbon
     */
    public function getDate(): Carbon
    {
        if (null !== $this->bookingDate) {
            app('log')->debug('Returning book date');

            return $this->bookingDate;
        }
        if (null !== $this->valueDate) {
            app('log')->debug('Returning value date');

            return $this->valueDate;
        }
        app('log')->warning('Transaction has no date, return NOW.');

        return new Carbon(config('app.timezone'));
    }

    /**
     * Return transaction description, which depends on the values in the object:
     *
     * @return string
     */
    public function getDescription(): string
    {
        $description = '';
        if ('' !== $this->remittanceInformationUnstructured) {
            $description = $this->remittanceInformationUnstructured;
            app('log')->debug('Description is now remittanceInformationUnstructured');
        }

        // try other values as well (Revolut)
        if ('' === $description && count($this->remittanceInformationUnstructuredArray) > 0) {
            $description = implode(' ', $this->remittanceInformationUnstructuredArray);
            app('log')->debug('Description is now remittanceInformationUnstructuredArray');
        }
        if ('' === $description) {
            app('log')->debug('Description is now remittanceInformationStructured');
            $description = $this->remittanceInformationStructured;
        }
        if ('' === $description) {
            app('log')->debug('Description is now additionalInformation');
            $description = $this->additionalInformation;
        }
        $description = trim($description);

        if ('' === $description) {
            app('log')->warning(sprintf('Transaction "%s" has no description.', $this->getTransactionId()));
            $description = '(no description)';
        }

        return $description;
    }

    /**
     * Return IBAN of the destination account
     *
     * @return string|null
     */
    public function getDestinationIban(): ?string
    {
        app('log')->debug(__METHOD__);
        if ('' !== $this->creditorAccountIban) {
            $data      = ['iban' => $this->creditorAccountIban];
            $rules     = ['iban' => ['required', new Iban()]];
            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                app('log')->warning(sprintf('Destination IBAN is "%s" (creditor), but it is invalid, so ignoring', $this->creditorAccountIban));

                return null;
            }

            app('log')->debug(sprintf('Destination IBAN is "%s" (creditor)', $this->creditorAccountIban));

            return $this->creditorAccountIban;
        }
        app('log')->warning(sprintf('Transaction "%s" has no destination IBAN information.', $this->getTransactionId()));

        return null;
    }

    /**
     * Return name of the destination account
     *
     * @return string|null
     */
    public function getDestinationName(): ?string
    {
        app('log')->debug(__METHOD__);
        if ('' !== $this->creditorName) {
            app('log')->debug(sprintf('Destination name is "%s" (creditor)', $this->creditorName));

            return $this->creditorName;
        }
        app('log')->warning(sprintf('Transaction "%s" has no destination account name information.', $this->getTransactionId()));

        return null;
    }

    /**
     * Return IBAN of the destination account
     *
     * @return string|null
     */
    public function getDestinationNumber(): ?string
    {
        app('log')->debug(__METHOD__);
        if ('' !== $this->creditorAccountBban) {
            app('log')->debug(sprintf('Destination BBAN is "%s" (creditor)', $this->creditorAccountBban));

            return $this->creditorAccountBban;
        }
        app('log')->warning(sprintf('Transaction "%s" has no destination BBAN information.', $this->getTransactionId()));

        return null;
    }

    /**
     * Returns notes based on info in the transaction.
     *
     * @return string
     */
    public function getNotes(): string
    {
        $notes = '';
        if ('' !== $this->additionalInformation) {
            $notes = $this->additionalInformation;
        }

        // room for other fields

        return $notes;
    }

    /**
     * Return IBAN of the source account.
     *
     * @return string|null
     */
    public function getSourceIban(): ?string
    {
        app('log')->debug(__METHOD__);
        if ('' !== $this->debtorAccountIban) {
            $data      = ['iban' => $this->debtorAccountIban];
            $rules     = ['iban' => ['required', new Iban()]];
            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                app('log')->warning(sprintf('Source IBAN is "%s" (debtor), but it is invalid, so ignoring', $this->debtorAccountIban));

                return null;
            }

            app('log')->debug(sprintf('Source IBAN is "%s" (debtor)', $this->debtorAccountIban));

            return $this->debtorAccountIban;
        }
        app('log')->warning(sprintf('Transaction "%s" has no source IBAN information.', $this->getTransactionId()));

        return null;
    }

    /**
     * Return name of the source account.
     *
     * @return string|null
     */
    public function getSourceName(): ?string
    {
        app('log')->debug(__METHOD__);
        if ('' !== $this->debtorName) {
            app('log')->debug(sprintf('Source name is "%s" (debtor)', $this->debtorName));

            return $this->debtorName;
        }
        app('log')->warning(sprintf('Transaction "%s" has no source account name information.', $this->getTransactionId()));

        return null;
    }

    /**
     * Return account number of the source account.
     *
     * @return string|null
     */
    public function getSourceNumber(): ?string
    {
        app('log')->debug(__METHOD__);
        if ('' !== $this->debtorAccountBban) {
            app('log')->debug(sprintf('Source BBAN is "%s" (debtor)', $this->debtorAccountBban));

            return $this->debtorAccountBban;
        }
        app('log')->warning(sprintf('Transaction "%s" has no source BBAN information.', $this->getTransactionId()));

        return null;
    }

    /**
     * @return Carbon|null
     */
    public function getValueDate(): ?Carbon
    {
        if (null !== $this->valueDate) {
            app('log')->debug('Returning value date for getValueDate');

            return $this->valueDate;
        }
        app('log')->warning('Transaction has no valueDate, return NULL.');

        return null;
    }

    /**
     * Call this "toLocalArray" because we want to confusion with "fromArray", which is really based
     * on Nordigen information. Likewise, there is also "fromLocalArray".
     *
     * @return array
     */
    public function toLocalArray(): array
    {
        $return = [
            'additional_information'                    => $this->additionalInformation,
            'additional_information_structured'         => $this->additionalInformationStructured,
            'balance_after_transaction'                 => $this->balanceAfterTransaction->toLocalArray(),
            'bank_transaction_code'                     => $this->bankTransactionCode,
            'booking_date'                              => $this->bookingDate->toW3cString(),
            'check_id'                                  => $this->checkId,
            'creditor_agent'                            => $this->creditorAgent,
            'creditor_id'                               => $this->creditorId,
            'creditor_name'                             => $this->creditorName,
            'currency_exchange'                         => $this->currencyExchange,
            'debtor_agent'                              => $this->debtorAgent,
            'debtor_name'                               => $this->debtorName,
            'entry_reference'                           => $this->entryReference,
            'key'                                       => $this->key,
            'mandate_id'                                => $this->mandateId,
            'proprietary_bank'                          => $this->proprietaryBank,
            'purpose_code'                              => $this->purposeCode,
            'remittance_information_structured'         => $this->remittanceInformationStructured,
            'remittance_information_structured_array'   => $this->remittanceInformationStructuredArray,
            'remittance_information_unstructured'       => $this->remittanceInformationUnstructured,
            'remittance_information_unstructured_array' => $this->remittanceInformationUnstructuredArray,
            'transaction_id'                            => $this->getTransactionId(),
            'ultimate_creditor'                         => $this->ultimateCreditor,
            'ultimate_debtor'                           => $this->ultimateDebtor,
            'value_date'                                => $this->valueDate->toW3cString(),
            'account_identifier'                        => $this->accountIdentifier,
            // array values:
            'debtor_account'                            => [
                'iban'     => $this->debtorAccountIban,
                'currency' => $this->debtorAccountCurrency,
            ],
            'creditor_account'                          => [
                'iban'     => $this->creditorAccountIban,
                'currency' => $this->creditorAccountCurrency,
            ],
            'transaction_amount'                        => [
                'amount'   => $this->transactionAmount,
                'currency' => $this->currencyCode,
            ],

            // undocumented values:
            'end_to_end_id'                             => $this->endToEndId,
        ];

        return $return;
    }

    /**
     * @return string
     */
    public function getTransactionId(): string
    {
        return trim(preg_replace('/\s+/', ' ', $this->transactionId));
    }


}
