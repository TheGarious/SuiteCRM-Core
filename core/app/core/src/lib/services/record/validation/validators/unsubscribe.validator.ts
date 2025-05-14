/**
 * SuiteCRM is a customer relationship management program developed by SalesAgility Ltd.
 * Copyright (C) 2025 SalesAgility Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SALESAGILITY, SALESAGILITY DISCLAIMS THE
 * WARRANTY OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License
 * version 3, these Appropriate Legal Notices must retain the display of the
 * "Supercharged by SuiteCRM" logo. If the display of the logos is not reasonably
 * feasible for technical reasons, the Appropriate Legal Notices must display
 * the words "Supercharged by SuiteCRM".
 */
import {Injectable} from "@angular/core";
import {ValidatorInterface} from "../validator.Interface";
import {Record} from "../../../../common/record/record.model";
import {ViewFieldDefinition} from "../../../../common/metadata/metadata.model";
import {StandardValidationErrors, StandardValidatorFn} from "../../../../common/services/validators/validators.model";
import {AbstractControl} from "@angular/forms";

export const unsubscribeValidator = (viewField: ViewFieldDefinition, record: Record): StandardValidatorFn => (
    (control: AbstractControl): StandardValidationErrors | null => {

        const patterns = [new RegExp(/\{\{\s*unsubscribe_link\s*}}/g), new RegExp(/%7B%7B\s*unsubscribe_link\s*%7D%7D/)];

        let match = false;

        patterns.forEach((regex) => {
            if (regex.test(control.value)){
                match = true;
            }
        })

        if (match) {
            return null;
        }

        return {
            unsubscribeValidator: {
                value: control.value,
                message: {
                    labelKey: 'LBL_VALIDATION_ERROR_UNSUBSCRIBE_LINK',
                    context: {}
                }
            }
        }
    }
);

@Injectable({
    providedIn: 'root'
})
export class UnsubscribeValidator implements ValidatorInterface {

    constructor() {
    }

    applies(record: Record, viewField: ViewFieldDefinition): boolean {

        if (!viewField || !viewField.fieldDefinition) {
            return false;
        }

        if (record.attributes['type'] === 'transactional'){
            return false;
        }

        return viewField.type === 'html';
    }

    getValidator(viewField: ViewFieldDefinition, record: Record): StandardValidatorFn[] {
        if (!viewField || !viewField.fieldDefinition) {
            return [];
        }

        return [unsubscribeValidator(viewField, record)];
    }
}
