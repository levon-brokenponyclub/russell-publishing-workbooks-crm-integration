/**
 * Ninja Forms Model Fix
 * Ensures model.get functionality is available
 */
(function($) {
    'use strict';

    // Initialize fix when document is ready
    $(document).ready(function() {
        console.log('[DEBUG] Initializing Ninja Forms model fix');
        
        // Create a backup of the original Backbone.Model
        if (typeof Backbone !== 'undefined') {
            var originalModel = Backbone.Model;
            
            // Ensure model.get exists
            Backbone.Model = Backbone.Model.extend({
                get: function(attr) {
                    // If the native get exists, use it
                    if (typeof originalModel.prototype.get === 'function') {
                        return originalModel.prototype.get.call(this, attr);
                    }
                    // Fallback implementation
                    return this.attributes ? this.attributes[attr] : undefined;
                },
                
                // Ensure set exists
                set: function(key, value, options) {
                    if (typeof originalModel.prototype.set === 'function') {
                        return originalModel.prototype.set.call(this, key, value, options);
                    }
                    // Fallback implementation
                    if (typeof key === 'object') {
                        this.attributes = {...this.attributes, ...key};
                    } else {
                        if (!this.attributes) this.attributes = {};
                        this.attributes[key] = value;
                    }
                    return this;
                }
            });
            
            console.log('[DEBUG] Backbone Model patched with get/set methods');
        }
        
        // Fix for Ninja Forms models specifically
        if (typeof nfRadio !== 'undefined') {
            nfRadio.channel('form').on('render:view', function(view) {
                console.log('[DEBUG] Form view rendering, applying model fixes');
                
                if (view && view.model && !view.model.get) {
                    view.model.get = function(attr) {
                        return this.attributes ? this.attributes[attr] : undefined;
                    };
                    console.log('[DEBUG] Added get method to form model');
                }
                
                // Fix field models
                if (view && view.model && view.model.get('fields')) {
                    var fields = view.model.get('fields');
                    fields.each(function(fieldModel) {
                        if (fieldModel && !fieldModel.get) {
                            fieldModel.get = function(attr) {
                                return this.attributes ? this.attributes[attr] : undefined;
                            };
                        }
                    });
                    console.log('[DEBUG] Added get method to field models');
                }
            });
        }
        
        // Override jQuery.fn.init to catch any model initialization issues
        var originalInit = $.fn.init;
        $.fn.init = function() {
            try {
                return originalInit.apply(this, arguments);
            } catch (e) {
                console.warn('[DEBUG] jQuery init error caught:', e);
                // Return empty jQuery object as fallback
                return $();
            }
        };
        $.fn.init.prototype = originalInit.prototype;
        
        console.log('[DEBUG] jQuery initialization patched');
    });
    
    // Handle form initialization
    $(document).on('nfFormReady', function(e, form) {
        console.log('[DEBUG] Form ready, checking models');
        
        if (form && form.fields) {
            form.fields.forEach(function(field) {
                if (field && !field.get) {
                    field.get = function(attr) {
                        return this.attributes ? this.attributes[attr] : undefined;
                    };
                }
            });
        }
        
        console.log('[DEBUG] Form models checked and fixed if needed');
    });

})(jQuery);
