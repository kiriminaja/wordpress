<style>
    /* Start Filter */
    /* Container utama untuk kartu */
    .tab-histories {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
        gap: 16px;
        padding: 16px;
    }

    /* Setelan dasar untuk kartu */
    .tab-card {
        background-color: #ffffff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        overflow: hidden;
        text-decoration: none; 
        color: inherit; 
        cursor: pointer;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    /* Hover effect pada kartu */
    .tab-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    /* Header untuk tiap kartu */
    .tab-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 12px;
    }

    /* Ikon di atas */
    .tab-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background-color: #f5f5f5;
        border-radius: 50%;
        margin-bottom: 12px; 
    }

    /* Label teks */
    .tab-label {
        flex: 1;
        text-align: center;
        margin-bottom: 8px;
    }

    /* Teks kecil */
    .text-small {
        font-size: 14px;
        font-weight: 600;
        color: #333333;
    }

    /* Count package */
    .tab-count-package {
        background-color: #5508a3;
        color: #ffffff;
        font-size: 12px;
        font-weight: bold;
        border-radius: 12px;
        padding: 2px 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 24px;
    }

    /* Kartu aktif */
    .tab-card.active {
        background-color: #ede3ff;
        transform: scale(1.02);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        border: 2px solid #5508a3;
    }

    /* Responsif untuk layar kecil */
    @media (max-width: 600px) {
        .tab-histories {
            grid-template-columns: 1fr;
        }

        .tab-header {
            flex-direction: column;
            align-items: center;
        }

        .tab-icon {
            margin-bottom: 8px;
        }

        .text-small {
            font-size: 12px;
        }

        .tab-count-package {
            font-size: 10px;
        }
    }
    /* end Filter */

</style>

<div class="wrap">
    <h1>History Package</h1>

    <!-- Tab Status Transaksi -->
    <div class="tabgroup-filter">
        <?php include_once ( KJ_DIR . 'templates/history-package/view/tabfilter.php' ); ?>
    </div>
    
    <!-- List data Histori -->
    <form action="" method="GET">
        <?php $list_table->search_box( __( 'Search' ), 'search-box-id' ); ?>
        <?php $list_table->display(); ?>
        <input type="hidden" name="page" value="<?= esc_attr($_REQUEST['page']) ?>"/>
    </form>

</div>


<script>
document.addEventListener('DOMContentLoaded', () => { 

    // Active Tab Card When Clicked
    document.querySelectorAll('.tab-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.tab-card').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Click First Tab Filter
    const firstClick = document.querySelector('.tab-card');

    if( firstClick ){
        firstClick.click();
    }
});
</script>