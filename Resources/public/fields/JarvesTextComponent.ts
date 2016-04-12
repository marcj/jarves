import AbstractFieldType from './AbstractFieldType.ts';
import {Component, Input, Output, EventEmitter} from 'angular2/core';
import {each} from '../utils.ts';

@Component({
    selector: 'jarves-text',
    template: '<input type="text" class="jarves-Input-text" [ngModel]="model" (ngModelChange)="modelChange.next($event)" [placeholder]="placeholder" />'
})
export default class JarvesTextComponent {
    @Input() public placeholder = '';
    @Input() public model;
    @Output() public modelChange:EventEmitter = new EventEmitter();
}