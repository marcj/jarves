import {Directive, ElementRef, Renderer, Input} from 'angular2/core';

declare var $:any;

@Directive({
    selector: '[icon]'
})
export default class IconDirective {
    @Input() public icon:string = '';

    constructor(protected el: ElementRef, protected renderer: Renderer) {
    }

    ngAfterContentInit() {
        var cssClass;

        if ('#' === this.icon.substr(0, 1)) {
            cssClass = this.icon.substr(1);
            console.log('add class', this.el, cssClass);
            this.renderer.setElementClass(this.el, cssClass, false);
        }

        //
        // if (!$(this.el.nativeElement).text()) {
        //     this.renderer.setElementClass(this.el, 'icon-no-text', true);
        // }
    }
}