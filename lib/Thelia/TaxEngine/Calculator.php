<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\TaxEngine;

use Thelia\Exception\TaxEngineException;
use Thelia\Model\Country;
use Thelia\Model\Product;
use Thelia\Model\TaxRuleQuery;

/**
 * Class Calculator
 * @package Thelia\TaxEngine
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Calculator
{
    protected $taxRuleQuery = null;

    protected $taxRulesGroupedCollection = null;

    protected $product = null;
    protected $country = null;

    public function __construct()
    {
        $this->taxRuleQuery = new TaxRuleQuery();
    }

    public function load(Product $product, Country $country)
    {
        $this->product = null;
        $this->country = null;
        $this->taxRulesGroupedCollection = null;

        if($product->getId() === null) {
            throw new TaxEngineException('Product id is empty in Calculator::load', TaxEngineException::UNDEFINED_PRODUCT);
        }
        if($country->getId() === null) {
            throw new TaxEngineException('Country id is empty in Calculator::load', TaxEngineException::UNDEFINED_COUNTRY);
        }

        $this->product = $product;
        $this->country = $country;

        $this->taxRulesGroupedCollection = $this->taxRuleQuery->getTaxCalculatorGroupedCollection($product, $country);

        return $this;
    }

    public function getTaxAmount($amount)
    {
        if(false === filter_var($amount, FILTER_VALIDATE_FLOAT)) {
            throw new TaxEngineException('BAD AMOUNT FORMAT', TaxEngineException::BAD_AMOUNT_FORMAT);
        }

        if(null === $this->taxRulesGroupedCollection) {
            throw new TaxEngineException('Tax rules collection is empty in Calculator::getTaxAmount', TaxEngineException::UNDEFINED_TAX_RULES_COLLECTION);
        }

        $taxRateAmount = 0;
        foreach($this->taxRulesGroupedCollection as $taxRule) {
            $taxRateAmount += $taxRule->getTaxRuleRateSum();
        }

        return $amount * $taxRateAmount * 0.01;
    }

    public function getTaxedPrice($amount)
    {
        if(false === filter_var($amount, FILTER_VALIDATE_FLOAT)) {
            throw new TaxEngineException('BAD AMOUNT FORMAT', TaxEngineException::BAD_AMOUNT_FORMAT);
        }

        return $amount + $this->getTaxAmount($amount);
    }
}
