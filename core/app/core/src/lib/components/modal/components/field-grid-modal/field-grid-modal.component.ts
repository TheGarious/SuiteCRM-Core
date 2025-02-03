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

import {Component, Input} from '@angular/core';
import {ModalModule} from "../modal/modal.module";
import {FieldGridModule} from "../../../field-grid/field-grid.module";
import {ModalCloseFeedBack} from "../../../../common/components/modal/modal.model";
import {ButtonInterface} from "../../../../common/components/button/button.model";
import {NgbActiveModal} from "@ng-bootstrap/ng-bootstrap";
import {ModalFieldBuilder} from "../../../../services/record/field/modal-field.builder";
import {ButtonModule} from "../../../button/button.module";
import {SendTestEmail} from "../../../../services/process/processes/send-test-email/send-test-email";
import {FieldModule} from "../../../../fields/field.module";
import {Record} from "../../../../common/record/record.model";
import {emptyObject} from "../../../../common/utils/object-utils";
import {MessageService} from "../../../../services/message/message.service";
import {ViewFieldDefinition} from "../../../../common/metadata/metadata.model";

@Component({
  selector: 'scrm-field-grid-modal',
  standalone: true,
  imports: [
    ModalModule,
    FieldGridModule,
    ButtonModule,
    FieldModule
  ],
  templateUrl: './field-grid-modal.component.html',
})
export class FieldGridModalComponent {

  @Input() fields: ViewFieldDefinition[];
  @Input() titleKey: string = '';
  @Input() module: string;
  @Input() recordID: string;

  closeButton: ButtonInterface;
  sendTestEmail: ButtonInterface;

  constructor(
      public activeModal: NgbActiveModal,
      protected modalFieldBuilder: ModalFieldBuilder,
      protected emailProcess: SendTestEmail,
      protected message: MessageService
      ) {
  }

  ngOnInit(): void {
    this.buildFields();
    this.initButtons();
  }

  protected initButtons() {
    this.closeButton = {
      klass: 'btn btn-primary btn-sm mt-3 mb-3',
      labelKey: 'LBL_CLOSE',
      onClick: (): void => {
        this.activeModal.close({
          type: 'close-button'
        } as ModalCloseFeedBack);
      }
    } as ButtonInterface;

    this.sendTestEmail = {
      klass: 'btn btn-primary btn-sm mt-3 mb-3',
      labelKey: 'LBL_SEND',
      onClick: (): void => {
        this.send();
      }
    } as ButtonInterface;
  }

  protected buildFields() {
    const fields = [];

    Object.entries(this.fields).forEach(([key, field]) => {
      fields.push(this.modalFieldBuilder.buildModalField(this.module, field))
    })

    this.fields = fields;
  }

  protected send() {

    const response = this.getValues();

    if (emptyObject(response.fields)) {
      this.message.addWarningMessageByKey('LBL_SELECT_EMAIL_FOR_TEST');
      return;
    }

    this.emailProcess.send(response).subscribe({
      next: (response) => {
        this.activeModal.close({
          type: 'close-button'
        } as ModalCloseFeedBack);
      },
      error: () => {
      }
    })
  }

  protected getValues() {
    let response = {
      fields: {},
      module: this.module,
    };

    Object.entries(this.fields).forEach(([_, field]) => {

      if (field.type === 'line-items') {
        const items = field.items;
        const key = field.definition?.lineItems?.definition?.name ?? '';

        let values = this.getFieldFromItem(field.name, items, key);

        if (emptyObject(values)) {
          return;
        }

        response.fields[field.name] = {
          definition: field.definition,
          type: 'line-items',
          module: field?.definition?.module ?? this.module,
          value: values
        };

        return;
      }

      if (!field.value){
        return;
      }

      response.fields[field.name] = {
        definition: field.definition,
        type: field.type,
        module: field?.definition?.module ?? this.module,
        value: field.value
      }
    })

    return response;
  }

  protected getFieldFromItem(fieldName, items: Record[], key) {

    const values = [];

    Object.entries(items).forEach(([_, item]) => {
      if (item.fields[key].attributes[fieldName].value !== ''){
        values.push(item.fields[key].attributes[fieldName].value);
      }
    })

    return values;
  }
}
