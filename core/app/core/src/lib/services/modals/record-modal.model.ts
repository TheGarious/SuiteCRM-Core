import {ObjectMap} from "../../common/types/object-map";
import {WritableSignal} from "@angular/core";
import {StringMap} from "../../common/types/string-map";
import {NgbModalOptions} from "@ng-bootstrap/ng-bootstrap/modal/modal-config";
import {Record} from "../../common/record/record.model";

export interface RecordModalOptions {
    titleKey?: string;
    record?: Record;
    dynamicTitleKey?: string;
    descriptionKey?: string;
    mapFields?: ObjectMap;
    labelKey?: string;
    headerLabelKey?: string;
    dynamicDescriptionKey?: string;
    module: string;
    detached?: boolean;
    minimizable?: boolean;
    bodyClass?: string;
    footerClass?: string;
    wrapperClass?: string;
    headerClass?: string;
    descriptionLabelKey?: string;
    dynamicTitleContext?: string;
    mode: string;
    metadataView?: string;
    topButtonsDropdownLabelKey?: string;
    centered?: boolean;
    parentId?: string;
    backdrop?: boolean;
    dynamicDescriptionContext?: WritableSignal<StringMap>;
    parentModule?: string;
    scrollable?: boolean;
    size?: 'sm' | 'lg' | 'xl';
    modalOptions?: NgbModalOptions

    [key: string]: any;
}
