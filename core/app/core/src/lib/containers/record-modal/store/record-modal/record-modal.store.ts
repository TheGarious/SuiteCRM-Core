/**
 * SuiteCRM is a customer relationship management program developed by SalesAgility Ltd.
 * Copyright (C) 2024 SalesAgility Ltd.
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

import {inject, Injectable} from '@angular/core';
import {BehaviorSubject, combineLatest, combineLatestWith, Observable, of, Subscription} from 'rxjs';
import {
    deepClone, FieldDefinitionMap,
    FieldLogicMap, FieldMetadata, isVoid, ObjectMap, Panel, PanelRow, Record, ViewContext,
    ViewFieldDefinition, ViewFieldDefinitionMap, ViewMode
} from 'common';
import {catchError, distinctUntilChanged, finalize, map, take, tap} from 'rxjs/operators';
import {MetadataStore, RecordViewMetadata} from '../../../../store/metadata/metadata.store.service';
import {StateStore} from '../../../../store/state';
import {UserPreferenceStore} from '../../../../store/user-preference/user-preference.store';
import {RecordStoreFactory} from "../../../../store/record/record.store.factory";
import {RecordStore} from "../../../../store/record/record.store";
import {isEmpty} from "lodash-es";
import {
    RecordViewData,
    RecordViewModel,
    RecordViewState
} from "../../../../views/record/store/record-view/record-view.store.model";
import {FieldActionsAdapterFactory} from "../../../../components/field-layout/adapters/field.actions.adapter.factory";
import {RecordValidationHandler} from "../../../../services/record/validation/record-validation.handler";
import {Params} from "@angular/router";
import {LanguageStore} from "../../../../store/language/language.store";
import {PanelLogicManager} from "../../../../components/panel-logic/panel-logic.manager";
import {AppStateStore} from "../../../../store/app-state/app-state.store";
import {MessageService} from "../../../../services/message/message.service";
import {ViewStore} from "../../../../store/view/view.store";
import {NavigationStore} from "../../../../store/navigation/navigation.store";
import {ModuleNavigation} from "../../../../services/navigation/module-navigation/module-navigation.service";

const initialState: any = {
    module: '',
    recordID: '',
    loading: false,
    mode: 'detail',
    params: {
        returnModule: '',
        returnId: '',
        returnAction: ''
    }
};

@Injectable()
export class RecordModalStore extends ViewStore  implements StateStore {

    record$: Observable<Record>;
    stagingRecord$: Observable<Record>;
    loading$: Observable<boolean>;
    mode$: Observable<ViewMode>;
    viewContext$: Observable<ViewContext>;

    panels$: Observable<Panel[]>;
    panels: Panel[] = [];

    metadataLoading$: Observable<boolean>;
    protected metadataLoadingState: BehaviorSubject<boolean>;

    /**
     * View-model that resolves once all the data is ready (or updated).
     */
    vm$: Observable<any>;
    vm: any;
    data: RecordViewData;
    recordStore: RecordStore;

    /** Internal Properties */
    protected cache$: Observable<any> = null;
    protected internalState: RecordViewState = deepClone(initialState);
    protected store = new BehaviorSubject<RecordViewState>(this.internalState);
    protected state$ = this.store.asObservable();
    protected subs: Subscription[] = [];
    protected fieldSubs: Subscription[] = [];
    protected panelsSubject: BehaviorSubject<Panel[]> = new BehaviorSubject(this.panels);
    protected actionAdaptorFactory: FieldActionsAdapterFactory;
    protected recordValidationHandler: RecordValidationHandler;

    constructor(
        protected recordStoreFactory: RecordStoreFactory,
        protected appStateStore: AppStateStore,
        protected preferences: UserPreferenceStore,
        protected metadataStore: MetadataStore,
        protected languageStore: LanguageStore,
        protected panelLogicManager: PanelLogicManager,
        protected message: MessageService,
        protected navigationStore: NavigationStore,
        protected moduleNavigation: ModuleNavigation,
    ) {
        super(appStateStore, languageStore, navigationStore, moduleNavigation, metadataStore);

        this.actionAdaptorFactory = inject(FieldActionsAdapterFactory);
        this.panels$ = this.panelsSubject.asObservable();
        this.recordStore = recordStoreFactory.create(this.getViewFieldsObservable(), this.getRecordMetadata$());
        this.record$ = this.recordStore.state$.pipe(distinctUntilChanged());
        this.stagingRecord$ = this.recordStore.staging$.pipe(distinctUntilChanged());
        this.loading$ = this.state$.pipe(map(state => state.loading));
        this.metadataLoadingState = new BehaviorSubject(false);
        this.metadataLoading$ = this.metadataLoadingState.asObservable();
        this.mode$ = this.state$.pipe(map(state => state.mode));

        const data$ = this.record$.pipe(
            combineLatestWith(this.loading$),
            map(([record, loading]: [Record, boolean]) => {
                this.data = {record, loading} as RecordViewData;
                return this.data;
            })
        );

        this.vm$ = data$.pipe(
            combineLatestWith(this.appData$, this.metadata$),
            map(([data, appData, metadata]) => {
                this.vm = {data, appData, metadata} as RecordViewModel;
                return this.vm;
            }));

        this.viewContext$ = this.record$.pipe(map(() => this.getViewContext()));
        this.initPanels();
        this.recordValidationHandler = inject(RecordValidationHandler);
    }

    clear(): void {
        this.recordStore = null;
        this.cache$ = null;
        this.updateState(deepClone(initialState));
        this.subs = this.safeUnsubscription(this.subs);
        this.fieldSubs = this.safeUnsubscription(this.fieldSubs);
    }

    clearAuthBased(): void {
    }

    /**
     * Initial record load if not cached and update state.
     * Returns observable to be used in resolver if needed
     *
     * @param {string} module to use
     * @param {string} recordID to use
     * @param {string} mode to use
     * @param {object} params to set
     * @returns {object} Observable<any>
     */
    public init(module: string, recordID: string, mode = 'detail' as ViewMode, params: Params = {}): void {
        this.internalState.module = module;
        this.internalState.recordID = recordID;
        this.setMode(mode);
        this.metadataLoadingState.next(true);

        if (mode === 'create') {
            this.recordStore.init(
                {
                    id: '',
                    type: '',
                    module: module,
                    attributes: { assigned_user_id: this.appStateStore.getCurrentUser().id,
                        assigned_user_name: {
                            id: this.appStateStore.getCurrentUser().id,
                            user_name: this.appStateStore.getCurrentUser().userName
                        },
                    },
                } as Record,
                true
            );
            this.metadataLoadingState.next(false);
        } else {
            this.load().pipe(
                take(1),
                tap(() => {
                    this.metadataLoadingState.next(false);
                    this.parseParams(params);
                })).subscribe();
        }
    }


    /**
     * Load / reload record using current pagination and criteria
     *
     * @param {boolean} useCache if to use cache
     * @returns {object} Observable<RecordViewState>
     */
    public load(useCache = true): Observable<Record> {

        this.updateState({
            ...this.internalState,
            loading: true
        });

        return this.recordStore.retrieveRecord(
            this.internalState.module,
            this.internalState.recordID,
            useCache
        ).pipe(
            tap((data: Record) => {
                this.updateState({
                    ...this.internalState,
                    recordID: data.id,
                    module: data.module,
                    loading: false
                });
            })
        );
    }

    save(): Observable<Record> {
        this.appStateStore.updateLoading(`${this.internalState.module}-record-save`, true);

        this.updateState({
            ...this.internalState,
            loading: true
        });

        return this.recordStore.save().pipe(
            catchError(() => {
                this.message.addDangerMessageByKey('LBL_ERROR_SAVING');
                return of({} as Record);
            }),
            finalize(() => {
                this.setMode('detail' as ViewMode);
                this.appStateStore.updateLoading(`${this.internalState.module}-record-save`, false);
                this.updateState({
                    ...this.internalState,
                    loading: false
                });
            })
        );
    }

    get params(): { [key: string]: string } {
        return this.internalState.params || {};
    }

    set params(params: { [key: string]: string }) {
        this.updateState({
            ...this.internalState,
            params
        });
    }

    protected parseParams(params: Params = {}): void {
        if (!params) {
            return;
        }

        const currentParams = {...this.internalState.params};
        Object.keys(params).forEach(paramKey => {
            if (!isVoid(currentParams[paramKey])) {
                currentParams[paramKey] = params[paramKey];
                return;
            }
        });

        this.params = params;
    }

    /**
     * Get view fields observable
     *
     * @returns {object} Observable<ViewFieldDefinition[]>
     */
    protected getViewFieldsObservable(): Observable<ViewFieldDefinition[]> {
        return this.metadataStore.recordViewMetadata$.pipe(map((recordMetadata: RecordViewMetadata) => {
            const fieldsMap: ViewFieldDefinitionMap = {};

            recordMetadata.panels.forEach(panel => {
                panel.rows.forEach(row => {
                    row.cols.forEach(col => {
                        const fieldName = col.name ?? col.fieldDefinition.name ?? '';
                        fieldsMap[fieldName] = col;
                    });
                });
            });

            Object.keys(recordMetadata.vardefs).forEach(fieldKey => {
                const vardef = recordMetadata.vardefs[fieldKey] ?? null;
                if (!vardef || isEmpty(vardef)) {
                    return;
                }

                // already defined. skip
                if (fieldsMap[fieldKey]) {
                    return;
                }

                if (vardef.type == 'relate') {
                    return;
                }

                fieldsMap[fieldKey] = {
                    name: fieldKey,
                    vardefBased: true,
                    label: vardef.vname ?? '',
                    type: vardef.type ?? '',
                    display: vardef.display ?? '',
                    fieldDefinition: vardef,
                    metadata: vardef.metadata ?? {} as FieldMetadata,
                    logic: vardef.logic ?? {} as FieldLogicMap
                } as ViewFieldDefinition;
            });

            return Object.values(fieldsMap);
        }));
    }

    protected getRecordMetadata$(): Observable<ObjectMap> {
        return this.metadataStore.recordViewMetadata$.pipe(map((recordMetadata: RecordViewMetadata) => {
            return recordMetadata?.metadata ?? {};
        }));
    }

    protected updateState(state: RecordViewState): void {
        this.store.next(this.internalState = state);
    }

    setMode(mode: ViewMode): void {
        this.updateState({...this.internalState, mode});
    }

    getMode(): ViewMode {
        if (!this.internalState) {
            return null;
        }
        return this.internalState.mode;
    }

    getBaseRecord(): Record {
        if (!this.internalState) {
            return null;
        }
        return this.recordStore.getBaseRecord();
    }

    getViewContext(): ViewContext {
        return {
            module: this.getModuleName(),
            id: this.getRecordId(),
            record: this.getBaseRecord()
        } as ViewContext;
    }

    getModuleName(): string {
        return this.internalState.module;
    }

    getRecordId(): string {
        return this.internalState.recordID;
    }

    private safeUnsubscription(subscriptionArray: Subscription[]): Subscription[] {
        subscriptionArray.forEach(sub => {
            if (sub.closed) {
                return;
            }

            sub.unsubscribe();
        });
        subscriptionArray = [];

        return subscriptionArray;
    }

    protected initPanels(): void {
        const panelSub = combineLatest([
            this.metadataStore.recordViewMetadata$,
            this.stagingRecord$,
            this.languageStore.vm$,
        ]).subscribe(([meta, record, languages]) => {
            const panels = [];
            const module = (record && record.module) || '';

            this.safeUnsubscription(this.fieldSubs);
            meta.panels.forEach(panelDefinition => {
                const label = (panelDefinition.label)
                    ? panelDefinition.label.toUpperCase()
                    : this.languageStore.getFieldLabel(panelDefinition.key.toUpperCase(), module, languages);
                const panel = {label, key: panelDefinition.key, rows: []} as Panel;

                let adaptor = null;
                const tabDef = meta.templateMeta.tabDefs[panelDefinition.key.toUpperCase()] ?? null;
                if (tabDef) {
                    panel.meta = tabDef;
                }

                panelDefinition.rows.forEach(rowDefinition => {
                    const row = {cols: []} as PanelRow;
                    rowDefinition.cols.forEach(cellDefinition => {
                        const cellDef = {...cellDefinition};
                        const fieldActions = cellDefinition.fieldActions || null;
                        if (fieldActions) {
                            adaptor = this.actionAdaptorFactory.create('recordView', cellDef.name, this);
                            cellDef.adaptor = adaptor;
                        }
                        row.cols.push(cellDef);
                    });
                    panel.rows.push(row);
                });

                panel.displayState = new BehaviorSubject(tabDef?.display ?? true);
                panel.display$ = panel.displayState.asObservable();

                panels.push(panel);

                if (isEmpty(record?.fields) || isEmpty(tabDef?.displayLogic)) {
                    return;
                }

                Object.values(tabDef.displayLogic).forEach((logicDef) => {
                    if (isEmpty(logicDef?.params?.fieldDependencies)) {
                        return;
                    }

                    logicDef.params.fieldDependencies.forEach(fieldKey => {
                        const field = record.fields[fieldKey] || null;
                        if (isEmpty(field)) {
                            return;
                        }

                        this.fieldSubs.push(
                            field.valueChanges$.subscribe(() => {
                                this.panelLogicManager.runLogic(logicDef.key, field, panel, record, this.getMode());
                            }),
                        );
                    });
                });
            });
            this.panelsSubject.next(this.panels = panels);
            return panels;
        });

        this.subs.push(panelSub);
    }

    /**
     * Get record view metadata
     *
     * @returns {object} metadata RecordViewMetadata
     */
    protected getRecordViewMetadata(): RecordViewMetadata {
        const meta = this.metadataStore.get() || {};
        return meta.recordView || {} as RecordViewMetadata;
    }

    /**
     * Get vardefs
     *
     * @returns {object} vardefs FieldDefinitionMap
     */
    protected getVardefs(): FieldDefinitionMap {
        const meta = this.getRecordViewMetadata();
        return meta.vardefs || {} as FieldDefinitionMap;
    }
}
