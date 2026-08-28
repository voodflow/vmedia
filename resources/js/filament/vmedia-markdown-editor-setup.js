/**
 * Filament MarkdownEditor hook (setUpUsing) — adds vmedia library toolbar button only.
 * Loaded globally by VmediaPlugin; VmediaMarkdownEditor delegates here instead of overriding Filament markup.
 */
window.vmediaMarkdownEditorSetUp = function vmediaMarkdownEditorSetUp(editorComponent) {
    const root = editorComponent.$root ?? editorComponent.$el

    if (!root) {
        return
    }

    const componentKey = root.dataset.vmediaComponentKey

    if (!componentKey) {
        return
    }

    const libraryTitle =
        root.dataset.vmediaLibraryTitle ?? 'Attach from media library'

    const wire = editorComponent.$wire

    if (!wire?.mountFormComponentAction) {
        return
    }

    const openLibrary = (event) => {
        event.preventDefault()
        wire.mountFormComponentAction(componentKey, 'insertVmediaImage')
    }

    const bar = editorComponent.editor?.gui?.toolbar

    if (!bar || bar.querySelector('.vmedia-library')) {
        return
    }

    const separator = document.createElement('span')
    separator.className = 'separator'
    bar.appendChild(separator)

    const button = document.createElement('button')
    button.type = 'button'
    button.className = 'vmedia-library'
    button.title = libraryTitle
    button.setAttribute('aria-label', libraryTitle)
    button.addEventListener('click', openLibrary)
    bar.appendChild(button)
}
