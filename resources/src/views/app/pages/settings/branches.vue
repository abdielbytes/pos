<template>
  <div class="main-content">
    <breadcumb :page="$t('Branches')" :folder="$t('Settings')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <b-card class="wrapper" v-if="!isLoading">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="branches"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
        enabled: true,
        placeholder: $t('Search_this_table'),
      }"
        :select-options="{
          enabled: true ,
          clearSelectionText: '',
        }"
        @on-selected-rows-change="selectionChanged"
        :pagination-options="{
        enabled: true,
        mode: 'records',
        nextLabel: 'next',
        prevLabel: 'prev',
      }"
        styleClass="table-hover tableOne vgt-table"
      >
        <div slot="selected-row-actions">
          <button class="btn btn-danger btn-sm" @click="delete_by_selected()">{{$t('Del')}}</button>
        </div>
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button
            @click="New_branch()"
            class="btn-rounded"
            variant="btn btn-primary btn-icon m-1"
          >
            <i class="i-Add"></i>
            {{$t('Add')}}
          </b-button>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'actions'">
            <a @click="Edit_branch(props.row)" title="Edit" v-b-tooltip.hover>
              <i class="i-Edit text-25 text-success"></i>
            </a>
            <a title="Delete" v-b-tooltip.hover @click="Remove_branch(props.row.id)">
              <i class="i-Close-Window text-25 text-danger"></i>
            </a>
          </span>
        </template>
      </vue-good-table>
    </b-card>

    <validation-observer ref="Create_branch">
      <b-modal hide-footer size="lg" id="New_branch" :title="editmode?$t('Edit'):$t('Add')">
        <b-form @submit.prevent="Submit_branch">
          <b-row>
            <!-- Name -->
            <b-col md="6">
              <validation-provider
                name="Name"
                :rules="{ required: true}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Name') + ' ' + '*'">
                  <b-form-input
                    :placeholder="$t('Enter_Name_branch')"
                    :state="getValidationState(validationContext)"
                    aria-describedby="Name-feedback"
                    label="Name"
                    v-model="branch.name"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Name-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Phone
            <b-col md="6">
                <b-form-group :label="$t('Phone')">
                  <b-form-input
                    :placeholder="$t('Enter_Phone_branch')"
                    label="Phone"
                    v-model="branch.mobile"
                  ></b-form-input>
                </b-form-group>
            </b-col>

             Country
            <b-col md="6">
                <b-form-group :label="$t('Country')">
                  <b-form-input
                    :placeholder="$t('Enter_Country_branch')"
                    label="Country"
                    v-model="branch.country"
                  ></b-form-input>
                </b-form-group>
            </b-col>

            <!- City --
            <b-col md="6">
                <b-form-group :label="$t('City')">
                  <b-form-input
                    :placeholder="$t('Enter_City_branch')"
                    label="City"
                    v-model="branch.city"
                  ></b-form-input>
                </b-form-group>
            </b-col>

            <!- Email --
            <b-col md="6">
              <b-form-group :label="$t('Email')">
                <b-form-input
                  :placeholder="$t('Enter_Email_branch')"
                  label="Email"
                  v-model="branch.email"
                ></b-form-input>
              </b-form-group>
            </b-col>

            <!- Zip Code --
            <b-col md="6">
              <b-form-group :label="$t('ZipCode')">
                <b-form-input
                  :placeholder="$t('Enter_ZipCode_branch')"
                  label="ZipCode"
                  v-model="branch.zip"
                ></b-form-input>
              </b-form-group>
            </b-col> -->

            <b-col md="12" class="mt-3">
                <b-button variant="primary" type="submit"  :disabled="SubmitProcessing"><i class="i-Yes me-2 font-weight-bold"></i> {{$t('submit')}}</b-button>
                  <div v-once class="typo__p" v-if="SubmitProcessing">
                    <div class="spinner sm spinner-primary mt-3"></div>
                  </div>
            </b-col>

          </b-row>
        </b-form>
      </b-modal>
    </validation-observer>
  </div>
</template>

<script>
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "branch"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing:false,
      serverParams: {
        columnFilters: {},
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      selectedIds: [],
      totalRows: "",
      search: "",
      limit: "10",
      branches: [],
      editmode: false,
      branch: {
        id: "",
        name: "",
        mobile: "",
        email: "",
        zip: "",
        country: "",
        city: ""
      }
    };
  },

  computed: {
    columns() {
      return [
        {
          label: this.$t("Name"),
          field: "name",
          tdClass: "text-left",
          thClass: "text-left"
        }
      ];
    }
  },

  methods: {
    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_branches(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_branches(1);
      }
    },

    //---- Event Select Rows
    selectionChanged({ selectedRows }) {
      this.selectedIds = [];
      selectedRows.forEach((row, index) => {
        this.selectedIds.push(row.id);
      });
    },

    //---- Event Sort Change

    onSortChange(params) {
      this.updateParams({
        sort: {
          type: params[0].type,
          field: params[0].field
        }
      });
      this.Get_branches(this.serverParams.page);
    },

    //---- Event Search
    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_branches(this.serverParams.page);
    },

    //---- Validation State Form
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    //------------- Submit Validation Create & Edit branch
    Submit_branch() {
      this.$refs.Create_branch.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          if (!this.editmode) {
            this.Create_branch();
          } else {
            this.Update_branch();
          }
        }
      });
    },

    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    //------------------------------ Modal (create branch) -------------------------------\\
    New_branch() {
      this.reset_Form();
      this.editmode = false;
      this.$bvModal.show("New_branch");
    },

    //------------------------------ Modal (Update branch) -------------------------------\\
    Edit_branch(branch) {
      this.Get_branches(this.serverParams.page);
      this.reset_Form();
      this.branch = branch;
      this.editmode = true;
      this.$bvModal.show("New_branch");
    },

    //--------------------------Get ALL branches ---------------------------\\

    Get_branches(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get(
          "branches?page=" +
            page +
            "&SortField=" +
            this.serverParams.sort.field +
            "&SortType=" +
            this.serverParams.sort.type +
            "&search=" +
            this.search +
            "&limit=" +
            this.limit
        )
        .then(response => {
          this.branches = response.data.branches;
          this.totalRows = response.data.totalRows;

          // Complete the animation of theprogress bar.
          NProgress.done();
          this.isLoading = false;
        })
        .catch(response => {
          // Complete the animation of theprogress bar.
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    //------------------------------- Create branch ------------------------\\
    Create_branch() {
      this.SubmitProcessing = true;
      axios
        .post("branches", {
          name: this.branch.name,
          mobile: this.branch.mobile,
          email: this.branch.email,
          zip: this.branch.zip,
          country: this.branch.country,
          city: this.branch.city
        })
        .then(response => {
          this.SubmitProcessing = false;
          Fire.$emit("Event_branch");
          this.makeToast(
            "success",
            this.$t("Create.TitleWarhouse"),
            this.$t("Success")
          );
        })
        .catch(error => {
          this.SubmitProcessing = false;
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },

    //------------------------------- Update branch ------------------------\\
    Update_branch() {
      this.SubmitProcessing = true;
      axios
        .put("branches/" + this.branch.id, {
          name: this.branch.name,
          mobile: this.branch.mobile,
          email: this.branch.email,
          zip: this.branch.zip,
          country: this.branch.country,
          city: this.branch.city
        })
        .then(response => {
          this.SubmitProcessing = false;
          Fire.$emit("Event_branch");

          this.makeToast(
            "success",
            this.$t("Update.TitleWarhouse"),
            this.$t("Success")
          );
        })
        .catch(error => {
          this.SubmitProcessing = false;
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },

    //------------------------------- reset Form ------------------------\\
    reset_Form() {
      this.branch = {
        id: "",
        name: "",
        mobile: "",
        email: "",
        zip: "",
        country: "",
        city: ""
      };
    },

    //------------------------------- Delete branch ------------------------\\
    Remove_branch(id) {
      this.$swal({
        title: this.$t("Delete.Title"),
        text: this.$t("Delete.Text"),
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Delete.cancelButtonText"),
        confirmButtonText: this.$t("Delete.confirmButtonText")
      }).then(result => {
        if (result.value) {
          axios
            .delete("branches/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete.Deleted"),
                this.$t("Delete.branchDeleted"),
                "success"
              );

              Fire.$emit("Delete_branch");
            })
            .catch(() => {
              this.$swal(
                this.$t("Delete.Failed"),
                this.$t("Delete.Therewassomethingwronge"),
                "warning"
              );
            });
        }
      });
    },

    //---- Delete units by selection

    delete_by_selected() {
      this.$swal({
        title: this.$t("Delete.Title"),
        text: this.$t("Delete.Text"),
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Delete.cancelButtonText"),
        confirmButtonText: this.$t("Delete.confirmButtonText")
      }).then(result => {
        if (result.value) {
          // Start the progress bar.
          NProgress.start();
          NProgress.set(0.1);
          axios
            .post("branches/delete/by_selection", {
              selectedIds: this.selectedIds
            })
            .then(() => {
              this.$swal(
                this.$t("Delete.Deleted"),
                this.$t("Delete.branchDeleted"),
                "success"
              );

              Fire.$emit("Delete_branch");
            })
            .catch(() => {
              // Complete the animation of theprogress bar.
              setTimeout(() => NProgress.done(), 500);
              this.$swal(
                this.$t("Delete.Failed"),
                this.$t("Delete.Therewassomethingwronge"),
                "warning"
              );
            });
        }
      });
    }
  },

  //----------------------------- Created function-------------------\\

  created: function() {
    this.Get_branches(1);

    Fire.$on("Event_branch", () => {
      setTimeout(() => {
        this.Get_branches(this.serverParams.page);
        this.$bvModal.hide("New_branch");
      }, 500);
    });

    Fire.$on("Delete_branch", () => {
      setTimeout(() => {
        this.Get_branches(this.serverParams.page);
      }, 500);
    });
  }
};
</script>
