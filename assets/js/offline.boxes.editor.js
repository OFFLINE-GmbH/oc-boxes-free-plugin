(function () {
    function DOMReady(callback) {
        return document.readyState === "interactive" || document.readyState === "complete" ? callback() : document.addEventListener("DOMContentLoaded", callback)
    }

    let currentTarget
    let toolbar
    let label
    let editor
    let referenceIndicator
    let moveDownAction
    let moveUpAction
    let addBeforeAction
    let duplicateAction
    let deleteAction
    let placeholder
    let focus
    let tools

    const focusClass = 'oc-box--has-focus'

    function resetFocus() {
        currentTarget = null
        window.parent.document.dispatchEvent(new CustomEvent('boxes.box.focus', {detail: null}))
        document.querySelectorAll(`.${focusClass}`).forEach(el => el.classList.remove(focusClass))
        tools.style.visibility = 'hidden'
        focus.style.visibility = 'hidden'
    }

    /**
     * Set the focus to a given DOMNode.
     * @param node
     */
    function focusBox(node) {
        if (!node) {
            return
        }

        document.querySelectorAll(`.${focusClass}`).forEach(el => el.classList.remove(focusClass))

        window.parent.document.dispatchEvent(new CustomEvent('boxes.box.focus', {detail: node}))
        node.classList.add(focusClass)

        let focusTarget = node

        // See if a container is defined manually
        const container = node.querySelector('[data-boxes-container]')
        if (container) {
            focusTarget = container
        } else {
            // Try to be smart about containers
            const container = node.querySelector('.container, .wrapper')
            if (container) {
                focusTarget = container
            }
        }

        const padding = container ? Number(container.dataset.boxesContainer * 2) : 40
        const dimensions = focusTarget.getBoundingClientRect()
        const scrollTop = dimensions.top + document.scrollingElement.scrollTop
        const left = dimensions.left + document.scrollingElement.scrollLeft
        const toolsHeight = tools.querySelector('.oc-boxes-editor-tools__inner').offsetHeight
        const height = Math.max(dimensions.height, 60)

        let top = scrollTop + (toolsHeight / 2) - (padding / 2)
        let toolbarOffset = toolsHeight
        if (top < toolbarOffset) {
            top = 0
            toolbarOffset = 0
        }

        focus.style.top = `${top}px`
        focus.style.left = `${left}px`
        focus.style.height = `${height + padding}px`
        focus.style.width = `${dimensions.width}px`
        focus.style.transform = `translateY(-${toolbarOffset}px)`
        focus.style.visibility = 'visible'

        tools.style.left = `${left}px`
        tools.style.width = `${dimensions.width}px`
        tools.style.top = `${top}px`
        tools.style.transform = `translateY(-${toolbarOffset}px)`
        tools.style.visibility = `visible`

        label.innerText = node.dataset.boxName
        referenceIndicator.classList.toggle('oc-boxes-box-reference-indicator--visible', !!node.dataset.boxReference)

        let locked = []
        if (node.dataset.boxLocked) {
            locked = node.dataset.boxLocked.split(',')
        }

        let previousLocked = []
        if (node.previousElementSibling && node.previousElementSibling.dataset.boxLocked) {
            previousLocked = node.previousElementSibling.dataset.boxLocked.split(',')
        }

        const cannotMoveUp = node.classList.contains('oc-box--first') || locked.includes('position') || previousLocked.includes('position')
        const cannotMoveDown = node.classList.contains('oc-box--last') || locked.includes('position')
        const cannotDelete = locked.includes('deletion')
        const cannotAddBefore = locked.includes('position')

        // Disable/Enable move actions
        moveUpAction.classList.toggle('oc-boxes-toolbar__action--disabled', cannotMoveUp)
        moveDownAction.classList.toggle('oc-boxes-toolbar__action--disabled', cannotMoveDown)
        deleteAction.classList.toggle('oc-boxes-toolbar__action--disabled', cannotDelete)
        addBeforeAction.classList.toggle('oc-boxes-toolbar__action--disabled', cannotAddBefore)
    }

    // ocRequest sends a request using October's AJAX Framework API.
    function ocRequest(handler, payload = {}) {
        return new Promise((resolve, reject) => {
            fetch(location.href + '/', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-OCTOBER-REQUEST-HANDLER': handler,
                    'X-OCTOBER-REQUEST-PARTIALS': '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams(payload),
            })
                .then(response => response.json())
                .then(json => resolve(json))
                .catch(e => reject(e))
        })
    }

    // Apply returned partial updates from a ocRequest.
    function applyPartialUpdates(result) {
        for (let selector in result) {
            const target = document.querySelector(selector)
            if (target) {
                target.innerHTML = result[selector]
            }
        }
    }

    // returns a click event handler that triggers an iframe event.
    function iFrameEventHandler(eventName) {
        return e => {
            e.preventDefault()
            e.stopPropagation()

            let id = currentTarget.dataset.box

            // If the box is a reference, use that instead.
            if (currentTarget.dataset.boxReference) {
                id = currentTarget.dataset.boxReference
            }

            const detail = {id: id}
            const contextBorder = currentTarget.closest('[data-box-partial-contexts]')

            if (contextBorder) {
                detail['partial_contexts'] = contextBorder.dataset.boxPartialContexts.split('||')
            }

            window.parent.document.dispatchEvent(new CustomEvent(eventName, {
                detail: detail,
            }))
        }
    }

    function setup() {

        document.documentElement.classList.add('oc-boxes-edit-mode')

        // Move the editor tools to the Document's body.
        tools = document.querySelector('#oc-boxes-editor-tools')
        if (tools) {
            document.body.appendChild(tools)
        }
        focus = document.querySelector('#oc-boxes-box-focus')
        if (focus) {
            document.body.appendChild(focus)
        }

        toolbar = document.querySelector('.oc-boxes-toolbar')
        label = document.querySelector('.oc-boxes-box-label')
        editor = document.querySelector('.oc-boxes-editor')
        referenceIndicator = document.querySelector('.oc-boxes-box-reference-indicator')
        placeholder = document.querySelector('#oc-boxes-box-placeholder')
        addBeforeAction = toolbar.querySelector('.js-boxes-action-add-before')
        moveDownAction = toolbar.querySelector('.js-boxes-action-move-down')
        moveUpAction = toolbar.querySelector('.js-boxes-action-move-up')
        deleteAction = toolbar.querySelector('.js-boxes-action-delete')
        duplicateAction = toolbar.querySelector('.js-boxes-action-duplicate')

        // Toolbar Actions
        addBeforeAction.addEventListener('click', iFrameEventHandler('boxes.box.add_before'))
        moveUpAction.addEventListener('click', iFrameEventHandler('boxes.box.move_up'))
        moveDownAction.addEventListener('click', iFrameEventHandler('boxes.box.move_down'))
        deleteAction.addEventListener('click', iFrameEventHandler('boxes.box.delete'))
        duplicateAction.addEventListener('click', iFrameEventHandler('boxes.box.duplicate'))

        document.body.addEventListener('mousemove', e => {
            // Do nothing when hovering over the info boxes.
            if (e.target.closest('#oc-boxes-editor-tools')) {
                return;
            }
            // The mouse is no longer over the editor.
            if (!e.target.closest('.oc-boxes-editor')) {
                resetFocus()
            }

            const box = e.target.closest('[data-box]')
            if (!box) {
                return
            }

            let targetChanged = currentTarget !== box

            currentTarget = box
            if (targetChanged) {
                focusBox(currentTarget)
            }
        })

        document.body.addEventListener('click', e => {
            const addBox = e.target.closest('.oc-boxes-add-box')
            if (addBox) {
                e.preventDefault()
                e.stopPropagation()

                const detail = {
                    parent_id: addBox.dataset.parentId,
                }

                const contextBorder = addBox.closest('[data-box-partial-contexts]')
                if (contextBorder) {
                    detail['partial_contexts'] = contextBorder.dataset.boxPartialContexts.split('||')
                }

                window.parent.document.dispatchEvent(new CustomEvent('boxes.box.add', {
                    detail: detail,
                }))
                return
            }

            if (!currentTarget) {
                return;
            }

            let locked = []

            if (currentTarget.dataset.boxLocked) {
                locked = currentTarget.dataset.boxLocked.split(',')
            }

            if (locked.includes('data')) {
                return;
            }

            window.parent.document.dispatchEvent(new CustomEvent('boxes.box.click', {detail: currentTarget}))
        })

        window.document.addEventListener('boxes.box.focus', e => {
            const element = document.querySelector(`[data-box="${e.detail}"]`)
            if (element) {
                focusBox(element)
                element.scrollIntoView({behavior: 'smooth', block: 'center'})
            }
        })

        window.document.addEventListener('boxes.box.add_placeholder', e => {
            placeholder.querySelector('.oc-boxes-box-placeholder__label').innerText = e.detail.partial_name
            placeholder.classList.add('visible')
            resetFocus()

            const preview = placeholder.querySelector('.oc-boxes-box-placeholder__preview')

            if (e.detail.preview) {
                preview.innerHTML = `<div class="oc-boxes-box-placeholder__loading">${spinner}</div>`;
                ocRequest('onRenderPlaceholder', {partial: e.detail.partial}).then(result => {
                    applyPartialUpdates(result)
                })
            } else {
                preview.innerHTML = ``;
            }

            const scrollOptions = {behavior: 'smooth', block: 'center'}
            if (e.detail.add_before) {
                const targetBox = document.querySelector(`[data-box="${e.detail.add_before}"]`)
                if (targetBox) {
                    targetBox.insertAdjacentElement('beforebegin', placeholder)
                    placeholder.scrollIntoView(scrollOptions)
                }
                return
            }

            if (e.detail.parent_id) {
                const targetBox = document.querySelector(`.oc-boxes-add-box--child[data-parent-id="${e.detail.parent_id}"]`)
                if (targetBox) {
                    targetBox.insertAdjacentElement('beforebegin', placeholder)
                    placeholder.scrollIntoView(scrollOptions)
                }
                return
            }

            editor.querySelector('.oc-boxes-editor__render').appendChild(placeholder)
            placeholder.scrollIntoView(scrollOptions)
        })

        window.document.addEventListener('boxes.refresh', e => {
            ocRequest('onRefreshBoxesPreview')
                .then((result) => {
                    applyPartialUpdates(result)
                    window.document.dispatchEvent(new CustomEvent('offline.boxes.editorRefreshed'))
                    resetFocus()
                })
        })

        // Boxes got added or removed.
        window.document.addEventListener('boxes.changed', e => {
            window.document.dispatchEvent(new CustomEvent('offline.boxes.editorRefreshed'))
        })

        window.addEventListener('resize', e => {
            if (currentTarget) {
                focusBox(currentTarget)
            }
        })
    }

    DOMReady(() => {
        setup()
    });

    // @credits https://github.com/n3r4zzurr0/svg-spinners/blob/main/svg-css/90-ring-with-bg.svg
    const spinner = `
        <svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor"><style>.spinner_ajPY{transform-origin:center;animation:spinner_AtaB .75s infinite linear}@keyframes spinner_AtaB{100%{transform:rotate(360deg)}}</style><path d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,19a8,8,0,1,1,8-8A8,8,0,0,1,12,20Z" opacity=".25"/><path d="M10.14,1.16a11,11,0,0,0-9,8.92A1.59,1.59,0,0,0,2.46,12,1.52,1.52,0,0,0,4.11,10.7a8,8,0,0,1,6.66-6.61A1.42,1.42,0,0,0,12,2.69h0A1.57,1.57,0,0,0,10.14,1.16Z" class="spinner_ajPY"/></svg>
    `

})();
