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

namespace App\Services\Spectre\Model;

use App\Services\CSV\Converter\Iban as IbanConverter;
use Carbon\Carbon;

/**
 * Class Transaction
 */
class Transaction
{
    public string           $accountId;
    public string           $amount;
    public string           $category;
    public Carbon           $createdAt;
    public string           $currencyCode;
    public string           $description;
    public bool             $duplicated;
    public TransactionExtra $extra;
    public string           $id;
    public Carbon           $madeOn;
    public string           $mode;
    public string           $status;
    public Carbon           $updatedAt;

    /**
     * Transaction constructor.
     */
    private function __construct()
    {
    }

    /**
     * Transaction constructor.
     *
     * @param array $data
     *
     * @return Transaction
     */
    public static function fromArray(array $data): self
    {
        $model               = new self();
        $model->id           = (string)$data['id'];
        $model->mode         = $data['mode'];
        $model->status       = $data['status'];
        $model->madeOn       = new Carbon($data['made_on']);
        $model->amount       = (string)$data['amount'];
        $model->currencyCode = $data['currency_code'];
        $model->description  = $data['description'];
        $model->category     = $data['category'];
        $model->duplicated   = $data['duplicated'];
        $model->extra        = TransactionExtra::fromArray($data['extra'] ?? []);
        $model->accountId    = $data['account_id'];
        $model->createdAt    = new Carbon($data['created_at']);
        $model->updatedAt    = new Carbon($data['updated_at']);

        return $model;
    }

    /**
     * @return string
     */
    public function getAccountId(): string
    {
        app('log')->debug(sprintf('Get getAccountId(): "%s"', $this->accountId));

        return $this->accountId;
    }

    /**
     * @return string
     */
    public function getAmount(): string
    {
        $amount = $this->amount;

        // if the number contains "E", it's in scientific notation, so we need to convert it to a normal number first.
        if (false !== stripos($amount, 'e')) {
            $amount = sprintf('%.12f', $amount);
        }

        return $amount;
    }

    /**
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * @return string
     */
    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        $description = app('steam')->cleanString($this->description);
        $additional  = $this->extra->getAdditional();
        if ('' === $description) {
            $description = '(no description)';
        }
        if (null !== $additional) {
            $description = trim(sprintf('%s %s', $description, app('steam')->cleanString($additional)));
        }

        return trim($description);
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return Carbon
     */
    public function getMadeOn(): Carbon
    {
        return $this->madeOn;
    }

    /**
     * @return Carbon
     */
    public function getPostingDate(): Carbon
    {
        return $this->extra->getPostingDate();
    }

    /**
     * @return string
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * @return string|null
     */
    public function getPayeeIban(): ?string
    {
        app('log')->debug('getPayeeIban()');
        $payee = $this->extra->getPayee();
        $valid = IbanConverter::isValidIban((string)$payee);
        // payee is valid IBAN:
        if ($valid) {
            app('log')->debug(sprintf('Payee IBAN is "%s", return this.', $payee));
            return (string)$payee;
        }
        // is not valid IBAN (also includes NULL)
        $payeeInfo = $this->extra->getPayeeInformation();
        $valid     = IbanConverter::isValidIban((string)$payeeInfo);
        if ($valid) {
            app('log')->debug(sprintf('Payee IBAN (payee information) is "%s", return this.', $payeeInfo));
            return (string)$payeeInfo;
        }
        app('log')->debug('Payee IBAN is "" (empty fallback), return NULL');
        return null;
    }

    /**
     * @return string|null
     */
    public function getPayee(): ?string
    {
        app('log')->debug('getPayee()');
        $payee     = (string)$this->extra->getPayee();
        $payeeInfo = (string)$this->extra->getPayeeInformation();
        $valid     = IbanConverter::isValidIban($payee);

        // if payee is IBAN, first see if payee information may be a better field:
        if ($valid && '' !== $payeeInfo) {
            app('log')->debug(sprintf('Payee is "%s", payee info is "%s", return payee info.', $payee, $payeeInfo));
            return $payeeInfo;
        }
        if (!$valid && '' === $payeeInfo) {
            app('log')->debug(sprintf('Payee is "%s", payee info is "%s", return payee.', $payee, $payeeInfo));
            return $payee;
        }
        if ($valid && '' === $payeeInfo) {
            app('log')->debug(sprintf('Payee is "%s", payee info is "%s", return payee.', $payee, $payeeInfo));
            return $payee;
        }
        if (!$valid && '' !== $payeeInfo) {
            app('log')->debug(sprintf('Payee is "%s", payee info is "%s", return payee info.', $payee, $payeeInfo));
            return $payeeInfo;
        }
        app('log')->debug(sprintf('Payee is "%s", payee info is "%s", return NULL.', $payee, $payeeInfo));

        // I think this covers everything, but you never know, so:
        return null;
        //return sprintf('(unknown %s account)', $direction);
    }

    /**
     * @return string|null
     */
    public function getPayerIban(): ?string
    {
        app('log')->debug('getPayerIban()');
        $payer = $this->extra->getpayer();
        $valid = IbanConverter::isValidIban((string)$payer);
        // payer is valid IBAN:
        if ($valid) {
            app('log')->debug(sprintf('Payer IBAN is "%s", return this.', $payer));
            return (string)$payer;
        }
        // is not valid IBAN (also includes NULL)
        $payerInfo = $this->extra->getpayerInformation();
        $valid     = IbanConverter::isValidIban((string)$payerInfo);
        if ($valid) {
            app('log')->debug(sprintf('Payer IBAN (payer information) is "%s", return thuis.', $payerInfo));
            return (string)$payerInfo;
        }
        app('log')->debug('Payer IBAN is "" (empty fallback), return NULL');
        return null;
    }

    /**
     * @return string|null
     */
    public function getPayer(): ?string
    {
        app('log')->debug('getPayer()');
        $payer     = (string)$this->extra->getPayer();
        $payerInfo = (string)$this->extra->getPayerInformation();
        $valid     = IbanConverter::isValidIban($payer);

        // if payer is IBAN, first see if payer information may be a better field:
        if ($valid && '' !== $payerInfo) {
            app('log')->debug(sprintf('Payer is "%s", payer info is "%s", return payer info.', $payer, $payerInfo));
            return $payerInfo;
        }
        if (!$valid && '' === $payerInfo) {
            app('log')->debug(sprintf('Payer is "%s", payer info is "%s", return payer.', $payer, $payerInfo));
            return $payer;
        }
        if ($valid && '' === $payerInfo) {
            app('log')->debug(sprintf('Payer is "%s", payer info is "%s", return payer.', $payer, $payerInfo));
            return $payer;
        }
        if (!$valid && '' !== $payerInfo) {
            app('log')->debug(sprintf('Payer is "%s", payer info is "%s", return payer info.', $payer, $payerInfo));
            return $payerInfo;
        }
        app('log')->debug(sprintf('Payer is "%s", payer info is "%s", return NULL.', $payer, $payerInfo));

        // I think this covers everything, but you never know, so:
        return null;
        //return sprintf('(unknown %s account)', $direction);
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'            => (string)$this->id,
            'account_id'    => $this->accountId,
            'made_on'       => $this->madeOn->toW3cString(),
            'created_at'    => $this->createdAt->toW3cString(),
            'updated_at'    => $this->updatedAt->toW3cString(),
            'mode'          => $this->mode,
            'status'        => $this->status,
            'amount'        => $this->amount,
            'currency_code' => $this->currencyCode,
            'description'   => (string)$this->description,
            'category'      => $this->category,
            'duplicated'    => $this->duplicated,
            'extra'         => $this->extra->toArray(),
        ];
    }
}
