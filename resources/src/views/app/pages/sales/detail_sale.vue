<template>
    <div class="main-content">
        <breadcumb :page="$t('SaleDetail')" :folder="$t('Sales')"/>
        <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

        <b-card v-if="!isLoading">
            <b-row>
                <b-col md="12" class="mb-5">
                    <router-link
                        v-if="currentUserPermissions && currentUserPermissions.includes('Sales_edit') && sale.sale_has_return == 'no'"
                        title="Edit"
                        class="btn btn-success btn-icon ripple btn-sm"
                        :to="{ name:'edit_sale', params: { id: $route.params.id } }"
                    >
                        <i class="i-Edit"></i>
                        <span>{{$t('EditSale')}}</span>
                    </router-link>

                    <button @click="Send_Email()" class="btn btn-info btn-icon ripple btn-sm">
                        <i class="i-Envelope-2"></i>
                        {{$t('Email')}}
                    </button>
                    <button @click="Sale_SMS()" class="btn btn-secondary btn-icon ripple btn-sm">
                        <i class="i-Speach-Bubble"></i>
                        SMS
                    </button>
                    <button @click="Sale_PDF()" class="btn btn-primary btn-icon ripple btn-sm">
                        <i class="i-File-TXT"></i>
                        PDF
                    </button>
                    <button @click="print()" class="btn btn-warning btn-icon ripple btn-sm">
                        <i class="i-Billing"></i>
                        {{$t('print')}}
                    </button>
                    <button
                        v-if="currentUserPermissions && currentUserPermissions.includes('Sales_delete') && sale.sale_has_return == 'no'"
                        @click="Delete_Sale()"
                        class="btn btn-danger btn-icon ripple btn-sm"
                    >
                        <i class="i-Close-Window"></i>
                        {{$t('Del')}}
                    </button>
                </b-col>
            </b-row>
            <div class="invoice" id="print_Invoice">
                <div class="invoice-print">
                    <b-row class="justify-content-md-center">
                        <h4 class="font-weight-bold">{{$t('SaleDetail')}} : {{sale.Ref}}</h4>
                    </b-row>
                    <hr>
                    <b-row class="mt-5">
                        <b-col lg="4" md="4" sm="12" class="mb-4">
                            <h5 class="font-weight-bold">{{$t('Customer_Info')}}</h5>
                            <div>{{sale.client_name}}</div>
                            <div>{{sale.client_email}}</div>
                            <div>{{sale.client_phone}}</div>
                            <div>{{sale.client_adr}}</div>
                        </b-col>
                        <b-col lg="4" md="4" sm="12" class="mb-4">
                            <h5 class="font-weight-bold">{{$t('Company_Info')}}</h5>
                            <div>{{company.CompanyName}}</div>
                            <div>{{company.email}}</div>
                            <div>{{company.CompanyPhone}}</div>
                            <div>{{company.CompanyAdress}}</div>
                        </b-col>
                        <b-col lg="4" md="4" sm="12" class="mb-4">
                            <h5 class="font-weight-bold">{{$t('Invoice_Info')}}</h5>
                            <div>{{$t('Reference')}} : {{sale.Ref}}</div>
                            <div>
                                {{$t('PaymentStatus')}} :
                                <span
                                    v-if="sale.payment_status == 'paid'"
                                    class="badge badge-outline-success"
                                >{{$t('Paid')}}</span>
                                <span
                                    v-else-if="sale.payment_status == 'partial'"
                                    class="badge badge-outline-primary"
                                >{{$t('partial')}}</span>
                                <span v-else class="badge badge-outline-warning">{{$t('Unpaid')}}</span>
                            </div>
                            <div>{{$t('branch')}} : {{sale.branch}}</div>
                            <div>
                                {{$t('Status')}} :
                                <span
                                    v-if="sale.statut == 'completed'"
                                    class="badge badge-outline-success"
                                >{{$t('complete')}}</span>
                                <span
                                    v-else-if="sale.statut == 'pending'"
                                    class="badge badge-outline-info"
                                >{{$t('Pending')}}</span>
                                <span v-else class="badge badge-outline-warning">{{$t('Ordered')}}</span>
                            </div>
                        </b-col>
                    </b-row>
                    <b-row class="mt-3">
                        <b-col md="12">
                            <h5 class="font-weight-bold">{{$t('Order_Summary')}}</h5>
                            <div class="table-responsive">
                                <table class="table table-hover table-md">
                                    <thead class="bg-gray-300">
                                    <tr>
                                        <th scope="col">{{$t('ProductName')}}</th>
                                        <th scope="col">{{$t('Net_Unit_Price')}}</th>
                                        <th scope="col">{{$t('Quantity')}}</th>
                                        <th scope="col">{{$t('UnitPrice')}}</th>
                                        <th scope="col">{{$t('Discount')}}</th>
                                        <th scope="col">{{$t('Tax')}}</th>
                                        <th scope="col">{{$t('SubTotal')}}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-for="detail in details" :key="detail.id">
                                        <td>
                                            <span>{{detail.code}} ({{detail.name}})</span>
                                            <p v-show="detail.is_imei && detail.imei_number !== null">{{$t('IMEI_SN')}} : {{detail.imei_number}}</p>
                                        </td>
                                        <td>{{currentUser.currency}} {{formatNumber(detail.Net_price, 3)}}</td>
                                        <td>{{formatNumber(detail.quantity, 2)}} {{detail.unit_sale}}</td>
                                        <td>{{currentUser.currency}} {{formatNumber(detail.price, 2)}}</td>
                                        <td>{{currentUser.currency}} {{formatNumber(detail.DiscountNet, 2)}}</td>
                                        <td>{{currentUser.currency}} {{formatNumber(detail.taxe, 2)}}</td>
                                        <td>{{currentUser.currency}} {{detail.total.toFixed(2)}}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </b-col>
                        <div class="offset-md-9 col-md-3 mt-4">
                            <table class="table table-striped table-sm">
                                <tbody>
                                <tr>
                                    <td>{{$t('OrderTax')}}</td>
                                    <td>
                                        <span>{{currentUser.currency}} {{sale.TaxNet.toFixed(2)}} ({{formatNumber(sale.tax_rate, 2)}} %)</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>{{$t('Discount')}}</td>
                                    <td>{{currentUser.currency}} {{sale.discount.toFixed(2)}}</td>
                                </tr>
                                <tr>
                                    <td>{{$t('Shipping')}}</td>
                                    <td>{{currentUser.currency}} {{sale.shipping.toFixed(2)}}</td>
                                </tr>
                                <tr>
                                    <td>
                                        <span class="font-weight-bold">{{$t('Total')}}</span>
                                    </td>
                                    <td>
                                        <span class="font-weight-bold">{{currentUser.currency}} {{sale.GrandTotal}}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <span class="font-weight-bold">{{$t('Paid')}}</span>
                                    </td>
                                    <td>
                                        <span class="font-weight-bold">{{currentUser.currency}} {{sale.paid_amount}}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <span class="font-weight-bold">{{$t('Due')}}</span>
                                    </td>
                                    <td>
                                        <span class="font-weight-bold">{{currentUser.currency}} {{sale.due}}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <span class="font-weight-bold">{{$t('Penalty')}}</span>
                                    </td>
                                    <td>
                                        <span class="font-weight-bold">{{currentUser.currency}} {{ sale.penalty }}</span>
                                    </td>
                                </tr>
                                <tr v-if="sale.sales_details && sale.sales_details.length > 0">
                                    <td>
                                        <span class="font-weight-bold">{{$t('Payment Frequency')}}</span>
                                    </td>
                                    <td>
                                        <span class="font-weight-bold">{{ sale.sales_details[0].payment_frequency }}</span>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </b-row>
                    <hr v-show="sale.note">
                    <b-row class="mt-4">
                        <b-col md="12">
                            <p>{{$t('sale_note')}} : {{sale.note}}</p>
                        </b-col>
                    </b-row>
                </div>
            </div>
        </b-card>
    </div>
</template>

<script>

import { mapGetters } from "vuex";
export default {
    metaInfo: {
        title: "Sale Details",
    },
    data() {
        return {
            isLoading: true,
            sale: {},
            details: [],
            company: {},
        };
    },
    computed: {
        ...mapGetters(["currentUser", "currentUserPermissions"]),
    },
    methods: {
        async getSaleDetails() {
            const id = this.$route.params.id;
            try {
                const response = await axios.get(`sales/${id}`);
                this.sale = response.data.sale;
                this.details = response.data.details;
                this.company = response.data.company;
                this.isLoading = false;
            } catch (error) {
                console.error("Error fetching sale details:", error);
            }
        },
        formatNumber(number, dec) {
            const value = (typeof number === "string") ? parseFloat(number) : number;
            return value.toFixed(dec);
        },
        async Sale_PDF() {
            const id = this.$route.params.id;
            const link = document.createElement("a");
            link.href = `sales/pdf/${id}?_q=${Date.now()}`;
            link.setAttribute("download", "sale_" + id + ".pdf");
            link.setAttribute("target", "_blank");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },
        async Send_Email() {
            const id = this.$route.params.id;
            try {
                await axios.post(`sales/send/email/${id}`);
                this.$bvToast.toast("Email sent successfully", {
                    title: "Success",
                    variant: "success",
                    solid: true,
                });
            } catch (error) {
                this.$bvToast.toast("Failed to send email", {
                    title: "Error",
                    variant: "danger",
                    solid: true,
                });
            }
        },
        async Sale_SMS() {
            const id = this.$route.params.id;
            try {
                await axios.post(`sales/send/sms/${id}`);
                this.$bvToast.toast("SMS sent successfully", {
                    title: "Success",
                    variant: "success",
                    solid: true,
                });
            } catch (error) {
                this.$bvToast.toast("Failed to send SMS", {
                    title: "Error",
                    variant: "danger",
                    solid: true,
                });
            }
        },
        print() {
            const divContents = document.getElementById("print_Invoice").innerHTML;
            const a = window.open("", "", "height=500, width=500");
            a.document.write("<html>");
            a.document.write("<body>");
            a.document.write(divContents);
            a.document.write("</body></html>");
            a.document.close();
            a.print();
        },
        async Delete_Sale() {
            const id = this.$route.params.id;
            this.$bvModal
                .msgBoxConfirm("Do you really want to delete this sale?", {
                    title: "Please Confirm",
                    size: "md",
                    buttonSize: "md",
                    okVariant: "danger",
                    okTitle: "YES",
                    cancelTitle: "NO",
                    footerClass: "p-2",
                    hideHeaderClose: false,
                    centered: true,
                })
                .then(async (value) => {
                    if (value) {
                        try {
                            await axios.delete(`sales/${id}`);
                            this.$router.push({ name: "index_sales" });
                            this.$bvToast.toast("Sale deleted successfully", {
                                title: "Success",
                                variant: "success",
                                solid: true,
                            });
                        } catch (error) {
                            this.$bvToast.toast("Failed to delete sale", {
                                title: "Error",
                                variant: "danger",
                                solid: true,
                            });
                        }
                    }
                });
        },
    },
    created() {
        this.getSaleDetails();
    },
};
</script>

<style scoped>
.invoice {
    background: white;
    padding: 15px;
}
</style>
