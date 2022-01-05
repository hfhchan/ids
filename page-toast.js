class PageToast extends HTMLElement{
	constructor() {
		super();	
		this.initialized = false;
		this.timer = 0;
	}
	
	connectedCallback() {
		if (this.initialized) {
			return;
		}
		this.initialized = true;
		this.style.position = 'fixed';
		this.style.zIndex = '9999999';
		this.style.bottom = '20px';
		this.style.width = 'max-content';
		this.style.padding = '10px 20px';
		this.style.left = '0px';
		this.style.right = '0px';
		this.style.margin = 'auto';
		this.style.borderRadius = '40px'
		this.style.background = 'rgba(0,0,0,.8)';
		this.style.color = '#fff';
		this.style.pointerEvents = 'none';
		this.style.opacity = '0';
		this.style.transition = 'opacity 150ms ease-out';
		this.textContent = 'toast';
	}
  
	show(msg) {
		if (this.timer) {
			window.clearTimeout(this.timer);
		}
		this.style.opacity = '1';
		this.textContent = msg;
		this.timer = window.setTimeout(this.hide.bind(this), 2000);
	}
	
	hide() {
		this.style.opacity = '0';
	}
	
}
customElements.define('page-toast', PageToast);