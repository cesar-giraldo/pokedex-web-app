import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['zone', 'input', 'message', 'fileName', 'clientError'];

    static values = {
        maxFileSize: Number,
        accept: String,
        acceptErrorMessage: String,
        maxFileSizeErrorMessage: String,
        disabled: Boolean,
    };

    static zoneHoverClasses = ['border-brand-500!', 'dark:border-brand-500!'];

    connect() {
        this.syncSelectedFileName();
    }

    openFileDialog(event) {
        if (this.disabledValue) {
            return;
        }

        if (event.target === this.inputTarget) {
            return;
        }

        event.preventDefault();
        this.inputTarget.click();
    }

    onDragOver(event) {
        event.preventDefault();

        if (this.disabledValue) {
            return;
        }

        this.zoneTarget.classList.add(...this.constructor.zoneHoverClasses);
    }

    onDragLeave(event) {
        event.preventDefault();
        this.zoneTarget.classList.remove(...this.constructor.zoneHoverClasses);
    }

    onDrop(event) {
        event.preventDefault();
        this.zoneTarget.classList.remove(...this.constructor.zoneHoverClasses);

        if (this.disabledValue) {
            return;
        }

        this.assignFiles(event.dataTransfer.files);
    }

    onInputChange() {
        this.validateFiles(this.inputTarget.files);
        this.syncSelectedFileName();
    }

    assignFiles(fileList) {
        const dataTransfer = new DataTransfer();
        const files = this.multipleEnabled()
            ? Array.from(fileList)
            : fileList.length > 0
              ? [fileList[0]]
              : [];

        files.forEach((file) => dataTransfer.items.add(file));
        this.inputTarget.files = dataTransfer.files;
        this.validateFiles(dataTransfer.files);
        this.syncSelectedFileName();
    }

    validateFiles(fileList) {
        if (fileList.length === 0) {
            this.clearClientError();
            this.inputTarget.setCustomValidity('');

            return true;
        }

        for (const file of fileList) {
            const validationMessage = this.validateFile(file);

            if (validationMessage !== null) {
                this.showClientError(validationMessage);
                this.inputTarget.setCustomValidity(validationMessage);
                this.inputTarget.value = '';
                this.syncSelectedFileName();

                return false;
            }
        }

        this.clearClientError();
        this.inputTarget.setCustomValidity('');

        return true;
    }

    validateFile(file) {
        if ('' !== this.acceptValue && !this.matchesAccept(file)) {
            return this.acceptErrorMessageValue;
        }

        if (this.maxFileSizeValue > 0 && file.size > this.maxFileSizeValue) {
            return this.maxFileSizeErrorMessageValue;
        }

        return null;
    }

    matchesAccept(file) {
        const tokens = this.acceptValue
            .split(',')
            .map((token) => token.trim().toLowerCase())
            .filter((token) => '' !== token);

        if (tokens.length === 0) {
            return true;
        }

        const fileName = file.name.toLowerCase();
        const fileType = file.type.toLowerCase();

        return tokens.some((token) => {
            if (token.startsWith('.')) {
                return fileName.endsWith(token);
            }

            if (token.endsWith('/*')) {
                return fileType.startsWith(token.slice(0, -1));
            }

            return fileType === token;
        });
    }

    syncSelectedFileName() {
        if (!this.hasFileNameTarget) {
            return;
        }

        if (this.inputTarget.files.length === 0) {
            this.fileNameTarget.textContent = '';
            this.fileNameTarget.classList.add('hidden');

            return;
        }

        const names = Array.from(this.inputTarget.files).map((file) => file.name);
        this.fileNameTarget.textContent = names.join(', ');
        this.fileNameTarget.classList.remove('hidden');
    }

    showClientError(message) {
        this.clientErrorTarget.textContent = message;
        this.clientErrorTarget.classList.remove('hidden');
        this.zoneTarget.classList.add('border-error-300!', 'dark:border-error-700!');
        this.zoneTarget.classList.remove('border-gray-300!', 'dark:border-gray-700!');
    }

    clearClientError() {
        this.clientErrorTarget.textContent = '';
        this.clientErrorTarget.classList.add('hidden');

        if (this.inputTarget.getAttribute('aria-invalid') !== 'true') {
            this.zoneTarget.classList.remove('border-error-300!', 'dark:border-error-700!');
            this.zoneTarget.classList.add('border-gray-300!', 'dark:border-gray-700!');
        }
    }

    multipleEnabled() {
        return this.inputTarget.hasAttribute('multiple');
    }
}
