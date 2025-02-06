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

import {combineLatestWith, Observable} from 'rxjs';
import {map, take} from 'rxjs/operators';
import {Injectable} from '@angular/core';
import {Action, ActionContext, ActionHandler, ModeActions} from '../../../common/actions/action.model';
import {LogicDefinitions} from '../../../common/metadata/metadata.model';
import {Record} from '../../../common/record/record.model';
import {ViewMode} from '../../../common/views/view.model';
import {Metadata, MetadataStore, RecordViewLayoutMetadataMap} from '../../../store/metadata/metadata.store.service';
import {RecordViewStore} from '../store/record-view/record-view.store';
import {AsyncActionInput, AsyncActionService,} from '../../../services/process/processes/async-action/async-action';
import {LanguageStore, LanguageStrings} from '../../../store/language/language.store';
import {MessageService} from '../../../services/message/message.service';
import {Process} from '../../../services/process/process.service';
import {ConfirmationModalService} from '../../../services/modals/confirmation-modal.service';
import {BaseRecordActionsAdapter} from '../../../services/actions/base-record-action.adapter';
import {SelectModalService} from '../../../services/modals/select-modal.service';
import {AppMetadataStore} from "../../../store/app-metadata/app-metadata.store.service";
import {RecordLayoutTabActionData} from "../actions/layout-tab-actions/layout-tab.action";
import {deepClone} from "../../../common/utils/object-utils";
import {
    RecordLayoutTabActionDisplayTypeLogic
} from "../actions/layout-tab-actions/action-logic/display-type/display-type.logic";
import {RecordLayoutTabActionManager} from "../actions/layout-tab-actions/layout-tab-action-manager.service";
import {toObservable} from "@angular/core/rxjs-interop";

@Injectable()
export class RecordLayoutTabActionsAdapter extends BaseRecordActionsAdapter<RecordLayoutTabActionData> {

    defaultActions: ModeActions = {
        detail: [],
        edit: [],
    };

    constructor(
        protected store: RecordViewStore,
        protected metadata: MetadataStore,
        protected language: LanguageStore,
        protected actionManager: RecordLayoutTabActionManager,
        protected asyncActionService: AsyncActionService,
        protected message: MessageService,
        protected confirmation: ConfirmationModalService,
        protected selectModalService: SelectModalService,
        protected displayTypeLogic: RecordLayoutTabActionDisplayTypeLogic,
        protected appMetadataStore: AppMetadataStore
    ) {
        super(
            actionManager,
            asyncActionService,
            message,
            confirmation,
            language,
            selectModalService,
            metadata,
            appMetadataStore
        );
    }

    getActions(context?: ActionContext): Observable<Action[]> {

        return this.store.metadata$.pipe(
            combineLatestWith(this.store.mode$, this.store.record$, this.store.language$, this.store.widgets$, this.store.layout$),
            map(([meta, mode]: [Metadata, ViewMode, Record, LanguageStrings, boolean, string]) => {
                const recordViewMetadata = meta?.recordView;


                const layouts = recordViewMetadata?.layouts ?? {};
                const layoutKeys = Object.keys(layouts);

                if (!recordViewMetadata) {
                    return [];
                }


                if (!layoutKeys.length) {
                    return [];
                }

                const orderedLayoutKeys = this.orderLayoutsKeys(layoutKeys, layouts);
                const actions = this.getTabActionsFromLayouts(orderedLayoutKeys, layouts);

                return this.parseModeActions(actions, mode, this.store.getViewContext());
            })
        );

    }

    protected buildActionData(action: Action, context?: ActionContext): RecordLayoutTabActionData {
        return {
            store: this.store,
            action,
        } as RecordLayoutTabActionData;
    }

    /**
     * Build backend process input
     *
     * @param {Action} action Action
     * @param {string} actionName Action Name
     * @param {string} moduleName Module Name
     * @param {ActionContext|null} context Context
     * @returns {AsyncActionInput} Built backend process input
     */
    protected buildActionInput(action: Action, actionName: string, moduleName: string, context: ActionContext = null): AsyncActionInput {
        const baseRecord = this.store.getBaseRecord();

        this.message.removeMessages();

        return {
            action: actionName,
            module: baseRecord.module,
            id: baseRecord.id,
            params: (action && action.params) || []
        } as AsyncActionInput;
    }

    protected getMode(): ViewMode {
        return this.store.getMode();
    }

    protected getModuleName(context?: ActionContext): string {
        return this.store.getModuleName();
    }

    protected reload(action: Action, process: Process, context?: ActionContext): void {
        this.store.load(false).pipe(take(1)).subscribe();
    }

    protected shouldDisplay(actionHandler: ActionHandler<RecordLayoutTabActionData>, data: RecordLayoutTabActionData): boolean {

        const displayLogic: LogicDefinitions | null = data?.action?.displayLogic ?? null;
        let toDisplay = true;

        if (displayLogic && Object.keys(displayLogic).length) {
            toDisplay = this.displayTypeLogic.runAll(displayLogic, data);
        }

        if (!toDisplay) {
            return false;
        }

        return actionHandler && actionHandler.shouldDisplay(data);
    }

    protected getTabActionsFromLayouts(orderedLayoutKeys: string[], layouts: RecordViewLayoutMetadataMap): Action[] {
        const actions = [];
        orderedLayoutKeys.forEach((layoutKey: string) => {
            const layout = layouts[layoutKey];

            if (!layout?.tabAction) {
                return;
            }

            const action = deepClone(layout.tabAction)

            if (!action?.key) {
                action.key = 'toggle';
            }

            if (!action?.labelKey) {
                action.labelKey = layoutKey;
            }

            if (!action?.params) {
                action.params = {} as { [key: string]: any };
                action.params.expanded = true;
            }

            action.params.layoutKey = layoutKey;

            if (!action?.modes) {
                action.modes = ['detail', 'edit'];
            }

            actions.push(action);
        });
        return actions;
    }

    protected orderLayoutsKeys(layoutKeys: string[], layouts: RecordViewLayoutMetadataMap): string[] {
        return layoutKeys.sort((a, b) => {
            return (layouts[a]?.order ?? 0) - (layouts[b]?.order ?? 0);
        });
    }
}
