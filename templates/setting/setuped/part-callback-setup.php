<span style="font-size: 18px; font-weight: 600">Webhooks</span>
<div class="row-divider" style="margin-top: .5rem"></div>
<span>This page is how the wordpress communicate with kiriminaja api</span><div class="kj-form">
    <table class="form-table">
        <tbody>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label >
                    Hooks
                </label>
            </th>
            <td class="forminp forminp-text">
                <input style="width: 100%; max-width: 25rem" name="callback_url" type="text" class="input-text regular-input" value="<?php echo @$inputValueArr['callback_url'];?>" >
            </td>
        </tr>
        </tbody>
    </table>
    
    <!--NOTICE-->
    <!--ALERT-->
    <div class="alert success" style="margin-top: .5rem">
        <div style="display: flex">
            <svg style="position: relative; top: 2px" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M7.99961 1.59998C11.5356 1.59998 14.3996 4.46398 14.3996 7.99998C14.3996 11.536 11.5356 14.4 7.99961 14.4C4.46361 14.4 1.59961 11.536 1.59961 7.99998C1.59961 4.46398 4.46361 1.59998 7.99961 1.59998ZM8.90361 9.10398L9.18361 3.93598H6.81561L7.09561 9.10398H8.90361ZM8.83161 11.792C9.02361 11.608 9.12761 11.352 9.12761 11.024C9.12761 10.688 9.03161 10.432 8.83961 10.248C8.64761 10.064 8.36761 9.96798 7.99161 9.96798C7.61561 9.96798 7.33561 10.064 7.13561 10.248C6.93561 10.432 6.83961 10.688 6.83961 11.024C6.83961 11.352 6.94361 11.608 7.14361 11.792C7.35161 11.976 7.63161 12.064 7.99161 12.064C8.35161 12.064 8.63161 11.976 8.83161 11.792Z" fill="#2271B1"/>
            </svg>

            <div style="margin-left: 8px">
                <div>
                    <span style="font-weight: 600">Note About Cache</span>
                </div>
                <div>
                    <span class="msg">The following endpoint above might be cached by wordpress system so you need to clear the cache by <a style="font-weight: 800" href="<?php echo  @home_url().'/wp-admin/options-permalink.php'?>">open here</a> and click on Save button.</span>
                </div>
            </div>
        </div>
    </div>

    <!--Btn Group-->
    <div class="submit-container">
        <div class="row-divider"></div>
        <div class="kj-btn-container">
            <button class="button-error woocommerce-save-button kj-disconnect" type="button">
                <div style="display: flex">
                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                        <svg style="position: relative; top: 1px" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14.1918 1.80799C14.8374 2.45591 15.1999 3.33331 15.1999 4.24799C15.1999 5.16267 14.8374 6.04007 14.1918 6.68799L12.9678 7.90399C12.7118 8.16799 12.4158 8.36799 12.1038 8.51999L10.3998 7.99999L11.7518 6.68799L12.3598 6.07199L12.9678 5.46399C13.6398 4.79199 13.6398 3.70399 12.9678 3.03199C12.8091 2.87041 12.6199 2.74206 12.4111 2.65445C12.2023 2.56683 11.9782 2.52171 11.7518 2.52171C11.5253 2.52171 11.3012 2.56683 11.0924 2.65445C10.8836 2.74206 10.6944 2.87041 10.5358 3.03199L9.91976 3.63999L9.31176 4.24799L7.99976 5.59999L7.47976 3.88799C7.63176 3.58399 7.83176 3.28799 8.09576 3.03199L9.31176 1.80799C9.95968 1.16236 10.8371 0.799835 11.7518 0.799835C12.6664 0.799835 13.5438 1.16236 14.1918 1.80799ZM1.59976 3.19999L7.99976 7.99999L3.19976 1.59999L1.59976 3.19999ZM4.79976 1.59999L7.99976 7.99999L6.39976 1.59999H4.79976ZM1.59976 4.79999L7.99976 7.99999L1.59976 6.39999V4.79999ZM7.48776 10.952L7.99976 10.4L8.59176 12.28L7.48776 13.392C6.83984 14.0376 5.96245 14.4001 5.04776 14.4001C4.13308 14.4001 3.25568 14.0376 2.60776 13.392C1.96213 12.7441 1.59961 11.8667 1.59961 10.952C1.59961 10.0373 1.96213 9.15991 2.60776 8.51199L3.71976 7.40799L5.59976 7.99999L5.04776 8.51199L3.83176 9.73599C3.15176 10.408 3.15176 11.496 3.83176 12.168C4.50376 12.848 5.59176 12.848 6.26376 12.168L7.48776 10.952ZM14.3998 12.8L7.99976 7.99999L12.7998 14.4L14.3998 12.8ZM11.1998 14.4L7.99976 7.99999L9.59976 14.4H11.1998ZM14.3998 11.2L7.99976 7.99999L14.3998 9.59999V11.2Z" fill="white"/>
                        </svg>

                        <span style="margin-left: 6px">Disconnect</span>
                    </div>
                </div>
            </button>
        </div>
        <div class="kj-btn-loader-container kj-hidden">
            <div class="kj-btn-loader" style="margin-top: auto; margin-bottom: auto; margin-left: .5rem"></div>
        </div>
    </div>
</div>